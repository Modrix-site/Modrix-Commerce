<?php

namespace App\Http\Controllers\Admin;

use App\Domains\Orders\Models\Invoice;
use App\Domains\Pricing\Money;
use App\Http\Controllers\Controller;
use App\Support\Like;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Orders → Invoices: every issued invoice, newest first (`can:orders.view`).
 * Invoices are issued from an order; this page only lists and links them.
 */
class InvoiceController extends Controller
{
    public function index(Request $request): Response
    {
        $filters = $request->validate(['search' => ['nullable', 'string', 'max:120']]);
        $search = trim((string) ($filters['search'] ?? ''));

        $invoices = Invoice::query()
            ->with('order:id,number,email')
            ->when($search !== '', function ($query) use ($search) {
                $query->where(function ($q) use ($search) {
                    Like::where($q, 'number', $search);
                    $q->orWhereHas('order', function ($order) use ($search) {
                        $order->where(function ($o) use ($search) {
                            Like::where($o, 'number', $search);
                            Like::orWhere($o, 'email', $search);
                        });
                    });
                });
            })
            ->latest('sequence')
            ->paginate(25)
            ->withQueryString()
            ->through(fn (Invoice $invoice) => [
                'id' => $invoice->id,
                'number' => $invoice->number,
                'issued_at' => $invoice->issued_at->toIso8601String(),
                'order_number' => $invoice->order?->number,
                'order_url' => $invoice->order ? route('admin.orders.show', $invoice->order) : null,
                'pdf_url' => $invoice->order ? route('admin.orders.documents.invoice', $invoice->order) : null,
                'buyer_name' => (string) ($invoice->buyer['name'] ?? ''),
                'buyer_email' => (string) ($invoice->buyer['email'] ?? $invoice->order?->email ?? ''),
                'total' => Money::of((string) ($invoice->totals['grand_total'] ?? '0'), $invoice->currency),
            ]);

        return Inertia::render('admin/invoices/index', [
            'invoices' => $invoices,
            'filters' => ['search' => $search],
        ]);
    }
}
