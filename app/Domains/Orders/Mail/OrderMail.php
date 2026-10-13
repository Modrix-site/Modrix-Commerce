<?php

namespace App\Domains\Orders\Mail;

use App\Core\Settings\StoreSettings;
use App\Domains\Orders\Documents\OrderDocuments;
use App\Domains\Orders\Enums\PaymentStatus;
use App\Domains\Orders\Models\Order;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Address;
use Illuminate\Mail\Mailables\Attachment;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;
use Throwable;

/**
 * Transactional order email. One Mailable, several kinds, so every message
 * shares the same summary and footer.
 */
class OrderMail extends Mailable
{
    use Queueable, SerializesModels;

    public const KINDS = [
        'placed' => 'Thank you for your order',
        'paid' => 'Payment received',
        'shipped' => 'Your order is on its way',
        'cancelled' => 'Your order was cancelled',
        'refunded' => 'Refund issued',
        'updated' => 'Update on your order',
        'staff_new_order' => 'New order received',
    ];

    public function __construct(
        public readonly Order $order,
        public readonly string $kind,
        public readonly ?string $paymentInstructions = null,
        public readonly ?string $note = null,
    ) {}

    public function envelope(): Envelope
    {
        $store = app(StoreSettings::class)->toArray();
        $replyTo = $store['store_email'] ? [new Address($store['store_email'], $store['store_name'])] : [];

        return new Envelope(
            subject: self::KINDS[$this->kind]." — {$this->order->number}",
            replyTo: $replyTo,
        );
    }

    /**
     * The payment email carries the invoice. A rendering failure must never
     * stop the email itself.
     *
     * @return list<Attachment>
     */
    public function attachments(): array
    {
        $paid = $this->kind === 'paid' || ($this->kind === 'placed' && $this->order->payment_status !== PaymentStatus::Unpaid);

        if (! $paid) {
            return [];
        }

        try {
            $documents = app(OrderDocuments::class);
            $invoice = $documents->issueInvoice($this->order);
            $pdf = $documents->invoicePdf($invoice);
        } catch (Throwable $e) {
            report($e);

            return [];
        }

        return [Attachment::fromData(fn () => $pdf, "invoice-{$invoice->number}.pdf")->withMime('application/pdf')];
    }

    public function content(): Content
    {
        $this->order->loadMissing('items', 'shippingAddress', 'shipments');

        return new Content(markdown: 'mail.orders.order', with: [
            'order' => $this->order,
            'kind' => $this->kind,
            'heading' => self::KINDS[$this->kind],
            'storeName' => app(StoreSettings::class)->name(),
            'paymentInstructions' => $this->paymentInstructions,
            'note' => $this->note,
            'shipment' => $this->order->shipments->last(),
        ]);
    }
}
