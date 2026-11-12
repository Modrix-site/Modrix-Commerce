<?php

namespace App\Http\Controllers\Admin;

use App\Domains\Orders\Documents\OrderDocuments;
use App\Domains\Orders\Models\Order;
use App\Domains\Payments\Models\Payment;
use App\Http\Controllers\Controller;
use App\Http\Responses\PdfDownload;
use Illuminate\Http\Response;

/**
 * Staff downloads of an order's invoice, payment receipts and packing slip.
 */
class OrderDocumentController extends Controller
{
    public function __construct(private readonly OrderDocuments $documents) {}

    public function invoice(Order $order): Response
    {
        $invoice = $order->invoice;
        abort_if($invoice === null, 404);

        return PdfDownload::make($this->documents->invoicePdf($invoice), "invoice-{$invoice->number}.pdf");
    }

    public function receipt(Order $order, Payment $payment): Response
    {
        abort_unless($payment->status === Payment::STATUS_PAID, 404);

        return PdfDownload::make($this->documents->receiptPdf($payment), "receipt-{$order->number}-{$payment->id}.pdf");
    }

    public function packingSlip(Order $order): Response
    {
        return PdfDownload::make($this->documents->packingSlipPdf($order), "packing-slip-{$order->number}.pdf");
    }
}
