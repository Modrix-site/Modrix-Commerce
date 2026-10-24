@extends('layouts.site', ['title' => 'Marketplace'])

@section('content')
    <section class="market-hero">
        <div class="hero-copy">
            <span class="eyebrow">THE MODRIX MARKETPLACE</span>
            <h1>Free and premium<br>themes and extensions.</h1>
            <p class="lead">{{ $tagline }}</p>
            <form class="market-search" method="get">
                <label class="sr-only" for="market-query">Search marketplace</label>
                <input id="market-query" name="q" aria-label="Search marketplace" placeholder="Search themes, extensions, or publishers" value="{{ $filters['q'] }}">
                <input type="hidden" name="type" value="{{ $type }}">
                <button>Search packages →</button>
            </form>
            <div class="hero-notes"><span>{{ $counts['theme'] }} storefront themes</span><span>{{ $counts['module'] }} extensions</span><span>Free &amp; paid packages</span></div>
        </div>
        <div class="hero-feature">
            <span class="hero-feature-icon" aria-hidden="true">▦</span>
            <span class="eyebrow">THE CORE PLATFORM</span>
            <h2>Built on Modrix Commerce.</h2>
            <p>Modrix Commerce runs the store: products, checkout, orders, customers, and payments. Themes and extensions in this repository plug into that core.</p>
            <a href="{{ url('/?type=theme') }}">Explore themes →</a>
        </div>
    </section>
    <nav class="market-type-tabs" aria-label="Package collections">
        @foreach (['all' => 'All packages', 'theme' => 'Themes', 'module' => 'Extensions'] as $value => $label)
            <a href="{{ $browseUrl(['type' => $value, 'category' => '']) }}" @if ($type === $value) aria-current="page" @endif>{{ $label }} <span>{{ $counts[$value] }}</span></a>
        @endforeach
    </nav>

    <div class="catalog-layout">
        <aside class="catalog-sidebar">
            <div class="sidebar-heading">Browse categories</div>
            <a class="category-link{{ $filters['category'] === '' ? ' active' : '' }}" href="{{ $browseUrl(['category' => '']) }}">All categories <span>{{ $typeCount }}</span></a>
            @foreach ($categories as $category)
                <a class="category-link{{ $filters['category'] === $category['slug'] ? ' active' : '' }}" href="{{ $browseUrl(['category' => $category['slug']]) }}">{{ $category['name'] }}<span>{{ $category['count'] }}</span></a>
            @endforeach
            <div class="sidebar-note"><strong>Build your next store</strong><p>Find a theme for your brand. Add extensions as your store grows.</p><a href="{{ url('/submit') }}">Become a publisher →</a></div>
        </aside>
        <section class="catalog-results" id="catalog">
            <form class="catalog-toolbar" method="get">
                <input type="hidden" name="type" value="{{ $type }}">
                <input type="hidden" name="category" value="{{ $filters['category'] }}">
                <input type="hidden" name="q" value="{{ $filters['q'] }}">
                <label>Price
                    <select name="tier">
                        @foreach (['all' => 'All prices', 'free' => 'Free', 'premium' => 'Paid'] as $value => $label)
                            <option value="{{ $value }}" @selected($filters['tier'] === $value)>{{ $label }}</option>
                        @endforeach
                    </select>
                </label>
                <label>Sort by
                    <select name="sort">
                        @foreach (['featured' => 'Featured first', 'updated' => 'Recently updated', 'name' => 'Name', 'price_asc' => 'Price: low to high', 'price_desc' => 'Price: high to low'] as $value => $label)
                            <option value="{{ $value }}" @selected($filters['sort'] === $value)>{{ $label }}</option>
                        @endforeach
                    </select>
                </label>
                <label class="check"><input type="checkbox" name="demo" value="1" @checked($filters['demo'] === '1')> Has live demo</label>
                <button type="submit">Apply filters</button>
                <a class="clear-filters" href="{{ url('/') }}">Reset</a>
            </form>
            <div class="results-heading">
                <div>
                    <h2>{{ $heading }}</h2>
                    <p>{{ $result['total'] }} {{ $result['total'] === 1 ? 'package' : 'packages' }} found @if ($filters['q'] !== '') for “{{ $filters['q'] }}” @endif</p>
                </div>
                <span class="currency-note">Prices in {{ $currency }}</span>
            </div>
            @include('partials.grid', ['products' => $result['items']])
            @if ($result['pages'] > 1)
                <nav class="catalog-pagination" aria-label="Catalog pages">
                    @for ($i = 1; $i <= $result['pages']; $i++)
                        <a href="{{ $browseUrl(['page' => $i]) }}" @if ($i === $result['page']) aria-current="page" @endif>{{ $i }}</a>
                    @endfor
                </nav>
            @endif
        </section>
    </div>
@endsection
