@extends('layouts.admin', ['title' => 'Orders'])

@section('admin')
    <h1>Orders</h1>
    <div class="table-wrap">
        <table>
            <thead><tr><th>Date</th><th>Customer</th><th>Package</th><th>Amount</th><th>Status</th><th>License</th></tr></thead>
            <tbody>
            @forelse ($orders as $order)
                <tr>
                    <td>{{ $order->created_at->format('Y-m-d H:i') }}</td>
                    <td>{{ $order->user?->email }}</td>
                    <td>{{ $order->package?->name }}</td>
                    <td>{{ $order->currency }} {{ $order->amount }}</td>
                    <td><span class="status status-{{ $order->status }}">{{ ucfirst($order->status) }}</span></td>
                    <td>@if ($order->license)<code>{{ $order->license->key }}</code>@endif</td>
                </tr>
            @empty
                <tr><td colspan="6" class="meta">No orders yet.</td></tr>
            @endforelse
            </tbody>
        </table>
    </div>
    {{ $orders->links() }}
@endsection
