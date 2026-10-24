@if (count($products) === 0)
    <div class="empty-state">
        <h2>No matching packages</h2>
        <p>Try a different category or a broader search.</p>
        <a class="button secondary" href="{{ url('/') }}">Clear filters</a>
    </div>
@else
    <ul class="grid package-grid">
        @foreach ($products as $product)
            <li class="package-card">
                <a class="package-cover" href="{{ url('/item/'.$product['slug']) }}">@include('partials.visual', ['product' => $product])</a>
                <div class="package-info">
                    <div class="package-topline">
                        <span class="package-kind">{{ $product['type'] === 'theme' ? 'THEME' : 'EXTENSION' }}</span>
                        @if ($product['featured'])<span class="featured-badge">Featured</span>@endif
                    </div>
                    <h3><a href="{{ url('/item/'.$product['slug']) }}">{{ $product['name'] }}</a></h3>
                    <p class="publisher-line">by {{ $product['author'] }}</p>
                    <p class="package-summary">{{ $product['summary'] }}</p>
                    @include('partials.tags', ['product' => $product])
                    @if ($product['premium'])<p class="purchase-license-note">Purchase first to get a license key.</p>@endif
                    <div class="package-meta"><span>v{{ $product['version'] }}</span><span>Platform {{ $product['platform_version'] }}</span></div>
                    <div class="package-bottom"><strong>{{ $product['price_label'] }}</strong><a href="{{ url('/item/'.$product['slug']) }}">View details →</a></div>
                    @if (! empty($product['demo_url']))
                        <a class="card-demo" href="{{ $product['demo_url'] }}" target="_blank" rel="noopener noreferrer">Live demo ↗</a>
                    @endif
                </div>
            </li>
        @endforeach
    </ul>
@endif
