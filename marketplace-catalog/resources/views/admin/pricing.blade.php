@extends('layouts.admin', ['title' => 'Pricing'])

@section('admin')
    <h1>Pricing</h1>
    <p class="meta">Set every package's price in one place. Prices are in {{ app(\Marketplace\Services\Catalog::class)->currency() }}.</p>
    <form method="post" action="{{ url('/admin/catalog') }}">
        @csrf
        <div class="table-wrap">
            <table>
                <thead><tr><th>Package</th><th>Tier</th><th>Price</th></tr></thead>
                <tbody>
                @foreach ($packages as $package)
                    <tr>
                        <td>{{ $package->name }}<br><span class="meta">{{ $package->identifier }}</span></td>
                        <td>
                            <select name="prices[{{ $package->id }}][tier]" aria-label="Tier for {{ $package->name }}">
                                <option value="free" @selected($package->is_free)>Free</option>
                                <option value="premium" @selected(! $package->is_free)>Paid</option>
                            </select>
                        </td>
                        <td><input name="prices[{{ $package->id }}][price]" type="number" min="0" step="0.01" value="{{ $package->price }}" aria-label="Price for {{ $package->name }}"></td>
                    </tr>
                @endforeach
                </tbody>
            </table>
        </div>
        <button type="submit">Save prices</button>
    </form>
@endsection
