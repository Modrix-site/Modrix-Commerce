<?php

namespace App\Http\Controllers\Storefront;

use App\Domains\Orders\Documents\OrderDocuments;
use App\Domains\Orders\Models\Order;
use App\Domains\Payments\Models\Payment;
use App\Http\Controllers\Controller;
use App\Http\Responses\PdfDownload;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

/**
 * A signed-in customer's own invoice and payment receipts.
 */
class AccountOrderDocumentController extends Controller
{
    public function __construct(private readonly OrderDocuments $documents) {}

    public function invoice(Request $request, Order $order): Response
    {
        $this->authorizeOwner($request, $order);
        $invoice = $order->invoice;
        abort_if($invoice === null, 404);

        return PdfDownload::make($this->documents->invoicePdf($invoice), "invoice-{$invoice->number}.pdf");
    }

    public function receipt(Request $request, Order $order, Payment $payment): Response
    {
        $this->authorizeOwner($request, $order);
        abort_unless($payment->status === Payment::STATUS_PAID, 404);

        return PdfDownload::make($this->documents->receiptPdf($payment), "receipt-{$order->number}-{$payment->id}.pdf");
    }

    private function authorizeOwner(Request $request, Order $order): void
    {
        // Not found rather than forbidden, so order numbers cannot be probed.
        abort_unless($order->user_id !== null && $order->user_id === $request->user()?->getAuthIdentifier(), 404);
    }
}
