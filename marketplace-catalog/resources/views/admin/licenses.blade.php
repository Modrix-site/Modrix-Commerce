@extends('layouts.admin', ['title' => 'Licenses'])

@section('admin')
    <h1>Licenses</h1>
    <form method="get" class="inline-form">
        <label class="sr-only" for="license-q">Search</label>
        <input id="license-q" name="q" value="{{ $q }}" placeholder="Key, email, package or store domain">
        <button type="submit" class="secondary">Search</button>
    </form>
    <div class="table-wrap">
        <table>
            <thead><tr><th>Key</th><th>Customer</th><th>Package</th><th>Status</th><th>Connected store</th><th></th></tr></thead>
            <tbody>
            @forelse ($licenses as $license)
                @php($activation = $license->activations->first())
                <tr>
                    <td><code>{{ $license->key }}</code><br><span class="meta">{{ ucfirst($license->type) }}</span></td>
                    <td>{{ $license->user?->email }}</td>
                    <td>{{ $license->package?->name }}</td>
                    <td><span class="status status-{{ $license->status }}">{{ ucfirst($license->status) }}</span></td>
                    <td>{{ $activation ? ($activation->domain ?: $activation->instance_id) : '—' }}</td>
                    <td class="actions">
                        @if ($activation)
                            <form method="post" action="{{ url('/admin/licenses/'.$license->id.'/release') }}">@csrf<button class="secondary small" type="submit">Disconnect</button></form>
                        @endif
                        @if ($license->status === 'active')
                            <form method="post" action="{{ url('/admin/licenses/'.$license->id.'/revoke') }}" onsubmit="return confirm('Revoke this license? Its store stops receiving updates.')">@csrf<button class="secondary small" type="submit">Revoke</button></form>
                        @endif
                    </td>
                </tr>
            @empty
                <tr><td colspan="6" class="meta">No licenses found.</td></tr>
            @endforelse
            </tbody>
        </table>
    </div>
    {{ $licenses->links() }}
@endsection
