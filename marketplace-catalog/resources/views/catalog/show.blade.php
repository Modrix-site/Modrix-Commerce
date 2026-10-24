@extends('layouts.site', ['title' => $product['name']])

@section('content')
    @php($kind = $product['type'] === 'theme' ? 'Theme' : 'Extension')
    <nav class="detail-breadcrumb" aria-label="Breadcrumb">
        <a href="{{ url('/') }}">Marketplace</a><span>/</span><a href="{{ url('/?type='.$product['type']) }}">{{ $kind }}s</a><span>/</span><span>{{ $product['name'] }}</span>
    </nav>
    <div class="detail-title">
        <div>
            <span class="eyebrow">{{ strtoupper($kind) }} FOR MODRIX COMMERCE</span>
            <h1>{{ $product['name'] }}</h1>
            <p>By <strong>{{ $product['author'] }}</strong> <span>· Version {{ $product['version'] }}</span></p>
        </div>
        @include('partials.tags', ['product' => $product])
    </div>
    <div class="product-layout">
        <div>
            <div class="product-preview">
                @include('partials.visual', ['product' => $product])
                <div class="preview-caption">
                    <span>{{ $product['name'] }} · {{ $product['type'] === 'theme' ? 'Storefront preview' : 'Extension overview' }}</span>
                    @if (! empty($product['demo_url']))
                        <a href="{{ $product['demo_url'] }}" target="_blank" rel="noopener noreferrer">Open live demo ↗</a>
                    @else
                        <span>No live demo provided</span>
                    @endif
                </div>
            </div>
            <section class="product-description">
                <h2>About this {{ $kind }}</h2>
                <p>{!! nl2br(e($product['summary'])) !!}</p>
                @if (! empty($product['features']))
                    <h3>Key features</h3>
                    <ul class="feature-list">
                        @foreach ($product['features'] as $feature)<li>{{ $feature }}</li>@endforeach
                    </ul>
                @endif
                @if (! empty($product['changelog']))
                    <h3>Latest release</h3>
                    <p>{!! nl2br(e($product['changelog'])) !!}</p>
                @endif
            </section>
        </div>
        <aside class="purchase-panel">
            <span class="package-kind">{{ $product['premium'] ? 'PAID '.strtoupper($kind) : 'FREE '.strtoupper($kind) }}</span>
            <p class="detail-price">{{ $product['price_label'] }}</p>
            <p class="meta">{{ $product['premium'] ? 'One-time purchase in '.$currency.'.' : 'Start building without a package fee.' }}</p>
            @if (! empty($product['demo_url']))
                <a class="button demo-button" href="{{ $product['demo_url'] }}" target="_blank" rel="noopener noreferrer">View live demo ↗</a>
            @endif
            @if ($product['premium'])
                <p class="purchase-license-note">Purchase this item first to get a license key in your account.</p>
            @endif
            @if ($owned)
                <p>You own this product. The license key is in your account.</p>
                <a class="button" href="{{ url('/account?product='.$product['slug']) }}">Manage license ↗</a>
            @elseif (! $product['premium'])
                <p>This product is free.</p>
                <a class="button" href="{{ auth()->check() ? url('/account?product='.$product['slug']) : url('/account/login?next='.rawurlencode('/account?product='.$product['slug'])) }}">Generate a free license ↗</a>
            @elseif (! auth()->check())
                <a class="button" href="{{ url('/account/login?next='.rawurlencode('/item/'.$product['slug'])) }}">Buy now</a>
            @else
                <form method="post" action="{{ url('/buy/'.$product['slug']) }}">@csrf<button type="submit">Buy now</button></form>
            @endif
            @if ($product['has_release'] && (! $product['premium'] || $owned))
                <a class="button secondary" href="{{ url('/download/'.$product['slug']) }}">Download package ZIP ↓</a>
            @endif
            <p class="license-note">One active store per license. Manage your key and connected store from your account.</p>
            <dl class="package-specs">
                @foreach (['Version' => $product['version'], 'Platform' => $product['platform_version'], 'License' => $product['license'], 'Publisher' => $product['author'], 'Package' => $product['identifier']] as $label => $value)
                    <div><dt>{{ $label }}</dt><dd>{{ $value }}</dd></div>
                @endforeach
            </dl>
            @if (! empty($product['repository_url']))
                <a class="source-link" href="{{ $product['repository_url'] }}" target="_blank" rel="noopener noreferrer">Source repository ↗</a>
            @endif
        </aside>
    </div>
    @if (count($related) > 0)
        <section class="related-packages">
            <div class="section-heading"><h2>More {{ strtolower($kind) }}s to explore</h2><a href="{{ url('/?type='.$product['type']) }}">View all →</a></div>
            @include('partials.grid', ['products' => $related])
        </section>
    @endif
@endsection
