@extends('layouts.site', ['title' => 'Submit a package'])

@section('content')
    <section class="panel form-panel">
        <span class="eyebrow">PUBLISHERS</span>
        <h1>Submit a package</h1>
        <p class="meta">Upload a ZIP with <code>module.json</code> (extension) or <code>theme.json</code> (theme) at its root. Its <code>name</code> and <code>version</code> must match the fields below. An admin reviews every submission before it is listed.</p>
        <form method="post" action="{{ url('/submit') }}" enctype="multipart/form-data" class="form-grid">
            @csrf
            <label>Name <input name="name" value="{{ old('name') }}" required maxlength="160"></label>
            <label>Package identifier <input name="identifier" value="{{ old('identifier') }}" required placeholder="vendor/package" pattern="[a-z0-9][a-z0-9\-]*/[a-z0-9][a-z0-9\-]*"></label>
            <label>Type
                <select name="type">
                    <option value="module" @selected(old('type') !== 'theme')>Extension</option>
                    <option value="theme" @selected(old('type') === 'theme')>Theme</option>
                </select>
            </label>
            <label>Version <input name="version" value="{{ old('version', '1.0.0') }}" required></label>
            <label>Platform version <input name="platform_version" value="{{ old('platform_version', '^1.0') }}" required></label>
            <label>Author <input name="author" value="{{ old('author', auth()->user()->name) }}" required maxlength="120"></label>
            <label>License <input name="license_name" value="{{ old('license_name', 'MIT') }}" required maxlength="64"></label>
            <label class="wide">Summary <textarea name="summary" required maxlength="255" rows="3">{{ old('summary') }}</textarea></label>
            <label>Price
                <select name="tier">
                    <option value="free" @selected(old('tier') !== 'premium')>Free</option>
                    <option value="premium" @selected(old('tier') === 'premium')>Paid</option>
                </select>
            </label>
            <label>Price ({{ config('marketplace.currency') }}) <input name="price" type="number" min="0" step="0.01" value="{{ old('price', '0') }}"></label>
            <label class="wide">Live demo URL (optional) <input name="demo_url" type="url" value="{{ old('demo_url') }}" placeholder="https://"></label>
            <label class="wide">Package ZIP <input type="file" name="package" accept=".zip,application/zip" required></label>
            <div class="wide"><button type="submit">Submit for review</button></div>
        </form>
    </section>
@endsection
