<?php

namespace App\Domains\Orders\Documents;

use App\Core\Audit\AuditLogger;
use App\Core\Localization\Locales;
use App\Core\Media\Media;
use App\Core\Settings\StoreSettings;
use App\Domains\Orders\Models\Invoice;
use App\Domains\Orders\Models\Order;
use App\Domains\Orders\Models\OrderAddress;
use App\Domains\Orders\Models\OrderEvent;
use App\Domains\Orders\Models\OrderItem;
use App\Domains\Orders\OrderWorkflow;
use App\Domains\Payments\Models\Payment;
use App\Domains\Pricing\Money;
use App\Models\User;
use BaconQrCode\Renderer\Image\SvgImageBackEnd;
use BaconQrCode\Renderer\ImageRenderer;
use BaconQrCode\Renderer\RendererStyle\RendererStyle;
use BaconQrCode\Writer;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use InvalidArgumentException;
use Throwable;

/**
 * Invoices, payment receipts and packing slips. Invoices are stored
 * snapshots; receipts and packing slips are rendered from records that do
 * not change (payments, refunds, order items).
 */
final class OrderDocuments
{
    public function __construct(
        private readonly InvoiceNumbers $numbers,
        private readonly DocumentSettings $settings,
        private readonly StoreSettings $store,
        private readonly AuditLogger $audit,
        private readonly PdfRenderer $pdf,
        private readonly Locales $locales,
    ) {}

    /**
     * Issues the order's invoice, or returns the one already issued.
     */
    public function issueInvoice(Order $order, ?User $actor = null): Invoice
    {
        if ($existing = Invoice::query()->where('order_id', $order->id)->first()) {
            return $existing;
        }

        return DB::transaction(function () use ($order, $actor): Invoice {
            /** @var Order $locked */
            $locked = Order::query()->whereKey($order->id)->lockForUpdate()->firstOrFail();

            if ($existing = Invoice::query()->where('order_id', $locked->id)->first()) {
                return $existing;
            }

            $locked->load('items', 'addresses');
            $settings = $this->settings->toArray();
            $store = $this->store->toArray();
            ['sequence' => $sequence, 'number' => $number] = $this->numbers->take();

            /** @var OrderAddress|null $billing */
            $billing = $locked->addresses->firstWhere('type', 'billing') ?? $locked->addresses->firstWhere('type', 'shipping');
            $name = $billing ? trim($billing->first_name.' '.$billing->last_name) : '';

            $invoice = new Invoice;
            $invoice->forceFill([
                'order_id' => $locked->id,
                'number' => $number,
                'sequence' => $sequence,
                'issued_at' => now(),
                'currency' => $locked->currency,
                'seller' => [
                    'name' => $store['store_name'],
                    'email' => $store['store_email'],
                    'address' => $settings['seller_address'],
                    'tax_id' => $settings['tax_id'],
                ],
                'buyer' => [
                    'name' => $name !== '' ? $name : $locked->email,
                    'email' => $locked->email,
                    'address' => $billing?->toPresentable(),
                ],
                'lines' => $locked->items->map(fn (OrderItem $item) => [
                    'name' => $item->product_name,
                    'variant' => $item->variant_name,
                    'sku' => $item->sku,
                    'quantity' => $item->quantity,
                    'unit_price' => $item->unit_price,
                    'tax_rate' => $item->tax_rate,
                    'tax' => $item->tax_total,
                    'discount' => $item->discount_total,
                    'total' => $item->total,
                ])->values()->all(),
                'totals' => [
                    'subtotal' => $locked->subtotal,
                    'discount_total' => $locked->discount_total,
                    'shipping_total' => $locked->shipping_total,
                    'tax_total' => $locked->tax_total,
                    'grand_total' => $locked->grand_total,
                    'prices_include_tax' => $locked->prices_include_tax,
                    'shipping_method' => $locked->shipping_method_name,
                    'payment_method' => $locked->payment_method_name,
                ],
                'notes' => $settings['footer'],
                'issued_by' => $actor?->id,
            ])->save();

            OrderEvent::query()->forceCreate([
                'order_id' => $locked->id,
                'type' => 'invoice_issued',
                'message' => "Invoice {$number} issued.",
                'is_customer_visible' => false,
                'actor_id' => $actor?->id,
            ]);
            $this->audit->record('orders.invoice_issued', $locked, ['number' => $locked->number, 'invoice' => $number], $actor);

            return $invoice;
        });
    }

    /**
     * The issued invoice, rendered from its snapshot only.
     */
    public function invoicePdf(Invoice $invoice): string
    {
        return $this->render('documents.invoice', fn () => $this->invoiceData($invoice));
    }

    /**
     * @return array<string, mixed>
     */
    private function invoiceData(Invoice $invoice): array
    {
        $invoice->loadMissing('order');
        $currency = $invoice->currency;

        return [
            'title' => __('Invoice'),
            'reference' => $invoice->number,
            'date' => $this->date($invoice->issued_at),
            'seller' => $invoice->seller,
            'invoice' => $invoice,
            'orderNumber' => $invoice->order->number,
            'money' => fn (string $amount) => Money::of($amount, $currency)->format($this->numberLocale()),
            'footer' => $invoice->notes,
        ];
    }

    /**
     * A receipt for one successful payment, with the order's balance after it.
     */
    public function receiptPdf(Payment $payment): string
    {
        return $this->render('documents.receipt', fn () => $this->receiptData($payment));
    }

    /**
     * @return array<string, mixed>
     */
    private function receiptData(Payment $payment): array
    {
        if ($payment->status !== Payment::STATUS_PAID) {
            throw new InvalidArgumentException('Receipts are only available for successful payments.');
        }

        $order = $payment->order;
        $paidToDate = Money::of('0', $order->currency);

        $paid = $order->payments()->where('status', Payment::STATUS_PAID)->orderBy('paid_at')->orderBy('id')->get();
        foreach ($paid as $p) {
            $paidToDate = $paidToDate->plus($p->money());

            if ($p->id === $payment->id) {
                break;
            }
        }

        $grand = $order->money('grand_total');
        $format = fn (Money $m) => $m->format($this->numberLocale());

        return [
            'title' => __('Payment receipt'),
            'reference' => $order->number.'-'.$payment->id,
            'date' => $this->date($payment->paid_at ?? $payment->created_at ?? now()),
            'seller' => $this->currentSeller(),
            'order' => $order,
            'payment' => $payment,
            'methodLabel' => OrderWorkflow::MANUAL_METHODS[$payment->method_code] ?? $order->payment_method_name,
            'amount' => $format($payment->money()),
            'grandTotal' => $format($grand),
            'paidToDate' => $format($paidToDate),
            'refunded' => $order->money('refunded_total')->isZero() ? null : $format($order->money('refunded_total')),
            'balance' => $format($grand->minus($paidToDate)->nonNegative()),
            'invoiceNumber' => $order->invoice?->number,
            'footer' => $this->settings->toArray()['footer'],
        ];
    }

    /**
     * What to pack and where to send it. No prices.
     */
    public function packingSlipPdf(Order $order): string
    {
        return $this->render('documents.packing-slip', fn () => $this->packingSlipData($order));
    }

    /**
     * @return array<string, mixed>
     */
    private function packingSlipData(Order $order): array
    {
        $order->loadMissing('items', 'addresses', 'shipments');
        $orderUrl = route('admin.orders.show', $order);
        $qr = new Writer(new ImageRenderer(
            new RendererStyle(240, 4),
            new SvgImageBackEnd,
        ));

        return [
            'title' => __('Packing slip'),
            'reference' => $order->number,
            'date' => $this->date($order->placed_at),
            'seller' => $this->currentSeller(),
            'order' => $order,
            'shipping' => $order->addresses->firstWhere('type', 'shipping')?->toPresentable(),
            'shipment' => $order->shipments->sortByDesc('id')->first(),
            'orderUrl' => $orderUrl,
            'orderQr' => 'data:image/svg+xml;base64,'.base64_encode($qr->writeString($orderUrl)),
            'footer' => null,
        ];
    }

    /**
     * Packing slips for several orders in one PDF (one per page).
     *
     * @param  iterable<Order>  $orders
     */
    public function packingSlipsPdf(iterable $orders): string
    {
        $docs = [];
        foreach ($orders as $order) {
            $docs[] = ['body' => 'documents.bodies.packing-slip', 'data' => fn () => $this->packingSlipData($order)];
        }

        return $this->renderBundle(fn () => __('Packing slips'), $docs, null);
    }

    /**
     * Several issued invoices in one PDF (one per page).
     *
     * @param  iterable<Invoice>  $invoices
     */
    public function invoicesPdf(iterable $invoices): string
    {
        $docs = [];
        foreach ($invoices as $invoice) {
            $docs[] = ['body' => 'documents.bodies.invoice', 'data' => fn () => $this->invoiceData($invoice)];
        }

        return $this->renderBundle(fn () => __('Invoices'), $docs, $this->settings->toArray()['footer']);
    }

    /**
     * Documents use the store's default language.
     *
     * @param  view-string  $view
     * @param  callable(): array<string, mixed>  $data
     */
    private function render(string $view, callable $data): string
    {
        return $this->inDocumentLocale(fn () => $this->pdf->render($view, [...$data(), 'logo' => $this->logo()]));
    }

    /**
     * @param  callable(): string  $title
     * @param  list<array{body: string, data: callable(): array<string, mixed>}>  $docs
     */
    private function renderBundle(callable $title, array $docs, ?string $footer): string
    {
        return $this->inDocumentLocale(function () use ($title, $docs, $footer) {
            $logo = $this->logo();

            return $this->pdf->render('documents.bundle', [
                'title' => $title(),
                'seller' => $this->currentSeller(),
                'footer' => $footer,
                'logo' => $logo,
                'docs' => array_map(fn (array $doc) => ['body' => $doc['body'], 'data' => [...($doc['data'])(), 'logo' => $logo]], $docs),
            ]);
        });
    }

    /**
     * @template T
     *
     * @param  callable(): T  $callback
     * @return T
     */
    private function inDocumentLocale(callable $callback): mixed
    {
        $previous = app()->getLocale();
        app()->setLocale($this->locales->default());

        try {
            return $callback();
        } finally {
            app()->setLocale($previous);
        }
    }

    /**
     * @return array{name: string, email: string|null, address: string|null, tax_id: string|null}
     */
    private function currentSeller(): array
    {
        $store = $this->store->toArray();
        $settings = $this->settings->toArray();

        return ['name' => $store['store_name'], 'email' => $store['store_email'], 'address' => $settings['seller_address'], 'tax_id' => $settings['tax_id']];
    }

    /**
     * The store logo as a data URI: documents never load URLs.
     */
    private function logo(): ?string
    {
        $id = $this->settings->toArray()['logo_media_id'];
        $media = $id === null ? null : Media::query()->find($id);

        if ($media === null || ! in_array($media->mime, ['image/png', 'image/jpeg', 'image/gif'], true)) {
            return null;
        }

        try {
            $bytes = Storage::disk($media->disk)->get($media->path);
        } catch (Throwable) {
            return null;
        }

        return $bytes === null ? null : 'data:'.$media->mime.';base64,'.base64_encode($bytes);
    }

    private function date(CarbonInterface $date): string
    {
        return $date->copy()->setTimezone($this->store->timezone())->locale(app()->getLocale())->translatedFormat('j M Y');
    }

    private function numberLocale(): string
    {
        return str_replace('-', '_', app()->getLocale());
    }
}
