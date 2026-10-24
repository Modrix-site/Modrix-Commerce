@extends('layouts.admin', ['title' => 'Settings'])

@section('admin')
    <h1>Settings</h1>
    <nav class="tabs" aria-label="Settings">
        @foreach ($tabs as $key => $label)
            <a href="{{ url('/admin/general?tab='.$key) }}" @if ($tab === $key) aria-current="page" @endif>{{ $label }}</a>
        @endforeach
    </nav>

    @if ($tab === 'general')
        <form method="post" action="{{ url('/admin/general') }}" enctype="multipart/form-data" class="form-grid">
            @csrf
            <label>Marketplace name <input name="catalog_name" value="{{ old('catalog_name', $general['catalog_name']) }}" required maxlength="120"></label>
            <label>Currency (ISO code) <input name="currency" value="{{ old('currency', $general['currency']) }}" required maxlength="3" pattern="[A-Za-z]{3}"></label>
            <label class="wide">Tagline <input name="tagline" value="{{ old('tagline', $general['tagline']) }}" maxlength="255"></label>
            <label>Support email <input type="email" name="support_email" value="{{ old('support_email', $general['support_email']) }}"></label>
            <label>Logo (PNG, JPEG or WebP) <input type="file" name="logo" accept="image/png,image/jpeg,image/webp"></label>
            @if ($general['logo'])
                <div class="wide"><img src="{{ url('/branding/logo') }}" alt="Current logo" class="logo-preview"> <label class="check"><input type="checkbox" name="remove_logo" value="1"> Remove logo</label></div>
            @endif
            <div class="wide"><button type="submit">Save settings</button></div>
        </form>
    @elseif ($tab === 'payments')
        <p class="meta">Stripe status: <strong>{{ $stripe['configured'] ? 'Ready for checkout' : 'Not configured' }}</strong>. Add a webhook endpoint in Stripe for <code>{{ $stripe['webhook_url'] }}</code> with the events <code>checkout.session.completed</code>, <code>checkout.session.expired</code>, <code>checkout.session.async_payment_succeeded</code>, <code>checkout.session.async_payment_failed</code> and <code>charge.refunded</code>.</p>
        <form method="post" action="{{ url('/admin/stripe') }}" class="form-grid">
            @csrf
            <label class="wide">Publishable key <input name="stripe_publishable" value="{{ old('stripe_publishable', $stripe['publishable']) }}" placeholder="pk_live_…" autocomplete="off"></label>
            <label class="wide">Secret key <input type="password" name="stripe_secret" value="" placeholder="{{ $stripe['secret_set'] ? 'Saved — leave blank to keep' : 'sk_live_…' }}" autocomplete="new-password"></label>
            <label class="wide">Webhook signing secret <input type="password" name="stripe_webhook_secret" value="" placeholder="{{ $stripe['webhook_set'] ? 'Saved — leave blank to keep' : 'whsec_…' }}" autocomplete="new-password"></label>
            <div class="wide"><button type="submit">Save Stripe settings</button></div>
        </form>
    @elseif ($tab === 'email')
        <form method="post" action="{{ url('/admin/smtp') }}" class="form-grid">
            @csrf
            <label>SMTP host <input name="smtp_host" value="{{ old('smtp_host', $smtp['host']) }}" placeholder="smtp.example.com"></label>
            <label>Port <input name="smtp_port" type="number" min="1" max="65535" value="{{ old('smtp_port', $smtp['port']) }}"></label>
            <label>Encryption
                <select name="smtp_encryption">
                    @foreach (['tls' => 'STARTTLS', 'ssl' => 'SSL/TLS', 'none' => 'None'] as $value => $label)
                        <option value="{{ $value }}" @selected($smtp['encryption'] === $value)>{{ $label }}</option>
                    @endforeach
                </select>
            </label>
            <label>Username <input name="smtp_username" value="{{ old('smtp_username', $smtp['username']) }}" autocomplete="off"></label>
            <label>Password <input type="password" name="smtp_password" value="" placeholder="{{ $smtp['password_set'] ? 'Saved — leave blank to keep' : '' }}" autocomplete="new-password"></label>
            <label>From address <input type="email" name="mail_from" value="{{ old('mail_from', $smtp['from']) }}"></label>
            <label>From name <input name="mail_from_name" value="{{ old('mail_from_name', $smtp['from_name']) }}"></label>
            <div class="wide"><button type="submit">Save email settings</button></div>
        </form>
        <form method="post" action="{{ url('/admin/smtp/test') }}">@csrf<button class="secondary" type="submit">Send a test email to {{ auth()->user()->email }}</button></form>
    @else
        <p>Stores verify license tokens and release files with this Ed25519 public key. In each store's <code>.env</code>, set <code>ECOMODULAR_MARKETPLACE_URL={{ url('/') }}</code> and add this key to <code>ECOMODULAR_MARKETPLACE_TRUSTED_KEYS</code> (keep any keys already there), then run <code>php artisan config:clear</code>.</p>
        <dl class="package-specs">
            <div><dt>Key ID</dt><dd><code>{{ $signing['key_id'] }}</code></dd></div>
            <div><dt>Public key</dt><dd><code class="license-key">{{ $signing['public_key'] }}</code></dd></div>
        </dl>
        <p class="meta">The signing secret never leaves the server. API base URL for stores: <code>{{ url('/api/marketplace/v1') }}</code></p>
    @endif
@endsection
