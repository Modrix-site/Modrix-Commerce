<?php

/** Catalog presentation and metadata, independent of installed store packages. */
function demoUrl(string $url): string
{
    if ($url !== '' && (strlen($url) > 2048 || ! filter_var($url, FILTER_VALIDATE_URL) || parse_url($url, PHP_URL_SCHEME) !== 'https' || parse_url($url, PHP_URL_USER) !== null || parse_url($url, PHP_URL_PASS) !== null)) {
        throw new RuntimeException('Use a valid HTTPS demo URL without embedded credentials.');
    }

    return $url;
}

function listingMetadata(Store $store, string $type, ?array $existing): array
{
    $categories = $_POST['categories'] ?? (isset($_POST['categories_present']) ? [] : ($existing['categories'] ?? []));
    if (! is_array($categories) || count($categories) > 12 || count(array_filter($categories, 'is_string')) !== count($categories)) {
        throw new RuntimeException('Select up to 12 valid categories.');
    }
    $allowed = array_column(array_filter($store->categories(isset($_SESSION['admin_id'])), fn ($c) => $c['type'] === 'all' || $c['type'] === $type), 'slug');
    if (array_diff($categories, $allowed) !== []) {
        throw new RuntimeException('Choose categories that match the package type.');
    }
    $features = isset($_POST['features_text']) ? array_values(array_filter(array_map('trim', preg_split('/\R/', field('features_text'))))) : ($existing['features'] ?? []);
    if (count($features) > 8 || count(array_filter($features, fn ($text) => mb_strlen($text) <= 120)) !== count($features)) {
        throw new RuntimeException('Add up to eight features, each up to 120 characters.');
    }

    return ['demo_url' => demoUrl(field('demo_url')), 'categories' => array_values(array_unique($categories)), 'features' => $features, 'featured' => isset($_SESSION['admin_id']) && field('featured') === '1'];
}

function listingMetadataForm(Store $store, array $p, bool $admin): string
{
    $html = '<section class="listing-discovery"><h3>Discovery & live preview</h3><label>Live demo URL<input type="url" name="demo_url" maxlength="2048" placeholder="https://demo.example.com" value="'.e($p['demo_url'] ?? '').'"></label><p class="meta">Optional public preview. Demos open in a new tab; HTTPS is required.</p><fieldset class="category-choices"><legend>Categories</legend><input type="hidden" name="categories_present" value="1">';
    foreach ($store->categories($admin) as $category) {
        $html .= '<label class="check" data-category-type="'.e($category['type']).'"><input type="checkbox" name="categories[]" value="'.e($category['slug']).'"'.(in_array($category['slug'], (array) ($p['categories'] ?? []), true) ? ' checked' : '').'>'.e($category['name']).' <small>'.e($category['type'] === 'theme' ? 'Themes' : ($category['type'] === 'module' ? 'Extensions' : 'All packages')).((int) $category['enabled'] === 0 ? ' · Hidden' : '').'</small></label>';
    }
    $html .= '</fieldset><label>Key features (one per line, up to eight)<textarea name="features_text" rows="4" maxlength="1000">'.e($p['features_text'] ?? implode("\n", $p['features'] ?? [])).'</textarea></label>';
    if ($admin) {
        $html .= '<label class="check"><input type="checkbox" name="featured" value="1"'.(! empty($p['featured']) ? ' checked' : '').'> Featured package</label>';
    }

    return $html.'</section>';
}

function categoryAdmin(Store $store): string
{
    $html = '<p class="lead">Organize themes by industry and extensions by what they do. Hide a category to remove it from public browsing without deleting its assignments.</p><div class="category-admin-grid">';
    foreach ([null, ...$store->categories(true)] as $category) {
        $new = $category === null;
        $category ??= ['slug' => '', 'name' => '', 'description' => '', 'type' => 'all', 'position' => 0, 'enabled' => 1];
        $html .= '<form method="post" action="/admin/categories" class="panel wide">'.csrf().'<h2>'.($new ? 'Add category' : e($category['name'])).'</h2><input type="hidden" name="original_slug" value="'.e($category['slug']).'"><label>Name<input name="name" maxlength="80" value="'.e($category['name']).'" required></label><label>Slug<input name="slug" pattern="[a-z0-9]+(-[a-z0-9]+)*" maxlength="80" value="'.e($category['slug']).'"'.($new ? '' : ' readonly').' required></label><label>Package type<select name="type">';
        foreach (['all' => 'Themes & extensions', 'theme' => 'Themes only', 'module' => 'Extensions only'] as $value => $label) {
            $html .= '<option value="'.$value.'"'.($category['type'] === $value ? ' selected' : '').'>'.$label.'</option>';
        }
        $html .= '</select></label><label>Description<textarea name="description" maxlength="500" rows="2">'.e($category['description']).'</textarea></label><label>Display order<input type="number" name="position" min="0" max="9999" value="'.(int) $category['position'].'"></label><label class="check"><input type="checkbox" name="enabled" value="1"'.((int) $category['enabled'] === 1 ? ' checked' : '').'> Visible in marketplace</label><button>'.($new ? 'Create category' : 'Save category').'</button></form>';
    }

    return $html.'</div>';
}

function browseUrl(array $changes = []): string
{
    $params = array_intersect_key($_GET, array_flip(['q', 'type', 'tier', 'category', 'demo', 'sort']));
    $params = array_replace($params, $changes);
    $params = array_filter($params, fn ($value) => is_scalar($value) && (string) $value !== '' && (string) $value !== 'all');

    return '/'.($params === [] ? '' : '?'.http_build_query($params));
}

function categoryTags(array $product): string
{
    $html = '<div class="package-tags">';
    foreach ($product['category_labels'] ?? [] as $category) {
        $html .= '<a href="/?category='.rawurlencode($category['slug']).'">'.e($category['name']).'</a>';
    }

    return $html.'</div>';
}

function grid(array $products): string
{
    if ($products === []) {
        return '<div class="empty-state"><h2>No matching packages</h2><p>Try a different category or a broader search.</p><a class="button secondary" href="/">Clear filters</a></div>';
    }
    $html = '<ul class="grid package-grid">';
    foreach ($products as $product) {
        $html .= '<li class="package-card"><a class="package-cover" href="/item/'.e($product['slug']).'">'.visual($product).'</a><div class="package-info"><div class="package-topline"><span class="package-kind">'.($product['type'] === 'theme' ? 'THEME' : 'EXTENSION').'</span>'.(! empty($product['featured']) ? '<span class="featured-badge">Featured</span>' : '').'</div><h3><a href="/item/'.e($product['slug']).'">'.e($product['name']).'</a></h3><p class="publisher-line">by '.e($product['author'] ?: 'Independent publisher').'</p><p class="package-summary">'.e((string) $product['summary']).'</p>'.categoryTags($product).($product['premium'] ? '<p class="purchase-license-note">Purchase first to get a license key.</p>' : '').'<div class="package-meta"><span>v'.e($product['version']).'</span><span>Platform '.e($product['platform_version']).'</span></div><div class="package-bottom"><strong>'.e($product['price_label']).'</strong><a href="/item/'.e($product['slug']).'">View details →</a></div>'.(! empty($product['demo_url']) ? '<a class="card-demo" href="'.e($product['demo_url']).'" target="_blank" rel="noopener noreferrer">Live demo ↗</a>' : '').'</div></li>';
    }

    return $html.'</ul>';
}

function marketplaceBrowse(Store $store, array $products, string $type, string $tier): string
{
    $query = trim(is_string($_GET['q'] ?? null) ? $_GET['q'] : '');
    $category = is_string($_GET['category'] ?? null) ? $_GET['category'] : '';
    $sort = in_array($_GET['sort'] ?? '', ['featured', 'updated', 'name', 'price_asc', 'price_desc'], true) ? $_GET['sort'] : 'featured';
    $demo = ($_GET['demo'] ?? '') === '1';
    $categories = array_filter($store->categories(), fn ($c) => $type === 'all' || $c['type'] === 'all' || $c['type'] === $type);
    $visible = array_values(array_filter($products, function ($p) use ($query, $category, $type, $tier, $demo) {
        return ($type === 'all' || $p['type'] === $type)
            && ($tier === 'all' || ($tier === 'premium' ? $p['premium'] : ! $p['premium']))
            && ($category === '' || isset($p['category_labels'][$category]))
            && (! $demo || ! empty($p['demo_url']))
            && ($query === '' || stripos($p['name'].' '.$p['summary'].' '.$p['author'].' '.$p['extension'].' '.implode(' ', array_column($p['category_labels'], 'name')), $query) !== false);
    }));
    usort($visible, fn ($a, $b) => match ($sort) {
        'price_asc' => (float) $a['price'] <=> (float) $b['price'] ?: strcasecmp($a['name'], $b['name']),
        'price_desc' => (float) $b['price'] <=> (float) $a['price'] ?: strcasecmp($a['name'], $b['name']),
        'updated' => strcmp($b['updated_at'], $a['updated_at']) ?: strcasecmp($a['name'], $b['name']),
        'name' => strcasecmp($a['name'], $b['name']),
        default => (int) $b['featured'] <=> (int) $a['featured'] ?: strcasecmp($a['name'], $b['name']),
    });
    $total = count($visible);
    $pages = max(1, (int) ceil($total / 12));
    $page = max(1, min($pages, (int) ($_GET['page'] ?? 1)));
    $html = '<div class="catalog-layout"><aside class="catalog-sidebar"><div class="sidebar-heading">Browse categories</div><a class="category-link'.($category === '' ? ' active' : '').'" href="'.e(browseUrl(['category' => ''])).'">All categories <span>'.count(array_filter($products, fn ($p) => $type === 'all' || $p['type'] === $type)).'</span></a>';
    foreach ($categories as $c) {
        $count = count(array_filter($products, fn ($p) => isset($p['category_labels'][$c['slug']]) && ($type === 'all' || $p['type'] === $type)));
        $html .= '<a class="category-link'.($category === $c['slug'] ? ' active' : '').'" href="'.e(browseUrl(['category' => $c['slug']])).'">'.e($c['name']).'<span>'.$count.'</span></a>';
    }
    $html .= '<div class="sidebar-note"><strong>Build your next store</strong><p>Find a theme for your brand. Add extensions as your store grows.</p><a href="/submit">Become a publisher →</a></div></aside><section class="catalog-results" id="catalog"><form class="catalog-toolbar" method="get"><input type="hidden" name="type" value="'.e($type).'"><input type="hidden" name="category" value="'.e($category).'"><input type="hidden" name="q" value="'.e($query).'"><label>Price<select name="tier">';
    foreach (['all' => 'All prices', 'free' => 'Free', 'premium' => 'Paid'] as $value => $label) {
        $html .= '<option value="'.$value.'"'.($tier === $value ? ' selected' : '').'>'.$label.'</option>';
    }
    $html .= '</select></label><label>Sort by<select name="sort">';
    foreach (['featured' => 'Featured first', 'updated' => 'Recently updated', 'name' => 'Name', 'price_asc' => 'Price: low to high', 'price_desc' => 'Price: high to low'] as $value => $label) {
        $html .= '<option value="'.$value.'"'.($sort === $value ? ' selected' : '').'>'.$label.'</option>';
    }
    $html .= '</select></label><label class="check"><input type="checkbox" name="demo" value="1"'.($demo ? ' checked' : '').'> Has live demo</label><button type="submit">Apply filters</button><a class="clear-filters" href="/">Reset</a></form><div class="results-heading"><div><h2>'.($category !== '' ? e(array_column($store->categories(), 'name', 'slug')[$category] ?? 'Category') : ($type === 'theme' ? 'Storefront themes' : ($type === 'module' ? 'Extensions for your store' : 'Explore the marketplace'))).'</h2><p>'.$total.' package'.($total === 1 ? '' : 's').' found'.($query === '' ? '' : ' for “'.e($query).'”').'</p></div><span class="currency-note">Prices in '.e($store->currency()).'</span></div>'.grid(array_slice($visible, ($page - 1) * 12, 12));
    if ($pages > 1) {
        $html .= '<nav class="catalog-pagination" aria-label="Catalog pages">';
        for ($i = 1; $i <= $pages; $i++) {
            $html .= '<a href="'.e(browseUrl(['page' => $i])).'"'.($i === $page ? ' aria-current="page"' : '').'>'.$i.'</a>';
        }
        $html .= '</nav>';
    }

    return $html.'</section></div>';
}

function marketplaceHero(Store $store, array $counts, string $type): string
{
    $tagline = $store->setting('tagline', 'The repository for Modrix Commerce. Choose a storefront theme or an extension, then run it on the core platform.');
    $html = '<section class="market-hero"><div class="hero-copy"><span class="eyebrow">THE MODRIX MARKETPLACE</span><h1>Free and premium<br>themes and extensions.</h1><p class="lead">'.e($tagline).'</p><form class="market-search" method="get"><label class="sr-only" for="market-query">Search marketplace</label><input id="market-query" name="q" aria-label="Search marketplace" placeholder="Search themes, extensions, or publishers" value="'.e(is_string($_GET['q'] ?? null) ? $_GET['q'] : '').'"><input type="hidden" name="type" value="'.e($type).'"><button>Search packages →</button></form><div class="hero-notes"><span>'.$counts['theme'].' storefront themes</span><span>'.$counts['module'].' extensions</span><span>Free & paid packages</span></div></div><div class="hero-feature"><span class="hero-feature-icon" aria-hidden="true">▦</span><span class="eyebrow">THE CORE PLATFORM</span><h2>Built on Modrix Commerce.</h2><p>Modrix Commerce runs the store: products, checkout, orders, customers, and payments. Themes and extensions in this repository plug into that core.</p><a href="/?type=theme">Explore themes →</a></div></section><nav class="market-type-tabs" aria-label="Package collections">';
    foreach (['all' => 'All packages', 'theme' => 'Themes', 'module' => 'Extensions'] as $value => $label) {
        $html .= '<a href="'.e(browseUrl(['type' => $value, 'category' => ''])).'"'.($type === $value ? ' aria-current="page"' : '').'>'.$label.' <span>'.$counts[$value].'</span></a>';
    }

    return $html.'</nav>';
}

function detail(array $product, Store $store, array $relatedProducts = []): string
{
    $customer = currentCustomer($store);
    $owned = $customer !== null && $store->owns((int) $customer['id'], $product['extension']);
    $kind = $product['type'] === 'theme' ? 'Theme' : 'Extension';
    $html = '<nav class="detail-breadcrumb" aria-label="Breadcrumb"><a href="/">Marketplace</a><span>/</span><a href="/?type='.e($product['type']).'">'.$kind.'s</a><span>/</span><span>'.e($product['name']).'</span></nav><div class="detail-title"><div><span class="eyebrow">'.strtoupper($kind).' FOR MODRIX COMMERCE</span><h1>'.e($product['name']).'</h1><p>By <strong>'.e($product['author']).'</strong> <span>· Version '.e($product['version']).'</span></p></div>'.categoryTags($product).'</div><div class="product-layout"><div><div class="product-preview">'.visual($product).'<div class="preview-caption"><span>'.e($product['name']).' · '.($product['type'] === 'theme' ? 'Storefront preview' : 'Extension overview').'</span>'.(! empty($product['demo_url']) ? '<a href="'.e($product['demo_url']).'" target="_blank" rel="noopener noreferrer">Open live demo ↗</a>' : '<span>No live demo provided</span>').'</div></div><section class="product-description"><h2>About this '.$kind.'</h2><p>'.nl2br(e((string) $product['summary'])).'</p>';
    if (! empty($product['features'])) {
        $html .= '<h3>Key features</h3><ul class="feature-list">';
        foreach ($product['features'] as $feature) {
            $html .= '<li>'.e($feature).'</li>';
        }
        $html .= '</ul>';
    }
    if (! empty($product['changelog'])) {
        $html .= '<h3>Latest release</h3><p>'.nl2br(e($product['changelog'])).'</p>';
    }
    $html .= '</section></div><aside class="purchase-panel"><span class="package-kind">'.($product['premium'] ? 'PAID '.$kind : 'FREE '.$kind).'</span><p class="detail-price">'.e($product['price_label']).'</p><p class="meta">'.($product['premium'] ? 'One-time purchase in '.e($store->currency()).'.' : 'Start building without a package fee.').'</p>';
    if (! empty($product['demo_url'])) {
        $html .= '<a class="button demo-button" href="'.e($product['demo_url']).'" target="_blank" rel="noopener noreferrer">View live demo ↗</a>';
    }
    if ($product['premium']) {
        $html .= '<p class="purchase-license-note">Purchase this item first to get a license key in your account.</p>';
    }
    if ($owned) {
        $html .= '<p>You own this product. The license key is in your account.</p><a class="button" href="/account?product='.e($product['slug']).'">Manage license ↗</a>';
    } elseif (! $product['premium']) {
        $html .= '<p>This product is free.</p><a class="button" href="'.($customer === null ? '/account/login?next='.rawurlencode('/account?product='.$product['slug']) : '/account?product='.e($product['slug'])).'">Generate a free license ↗</a>';
    } elseif ($customer === null) {
        $html .= '<a class="button" href="/account/login?next='.rawurlencode('/item/'.$product['slug']).'">Buy now</a>';
    } else {
        $html .= '<form method="post" action="/buy/'.e($product['slug']).'">'.csrf().'<button type="submit">Buy now</button></form>';
    }
    if (! empty($product['package']) && (! $product['premium'] || $owned)) {
        $html .= '<a class="button secondary" href="/download/'.e($product['slug']).'">Download package ZIP ↓</a>';
    }
    $html .= '<p class="license-note">One active store per license. Manage your key and connected store from your account.</p><dl class="package-specs">';
    foreach (['Version' => $product['version'], 'Platform' => $product['platform_version'], 'License' => $product['license'], 'Publisher' => $product['author'], 'Package' => $product['extension']] as $label => $value) {
        $html .= '<div><dt>'.e($label).'</dt><dd>'.e((string) $value).'</dd></div>';
    }
    $html .= '</dl>'.(! empty($product['repository_url']) ? '<a class="source-link" href="'.e($product['repository_url']).'" target="_blank" rel="noopener noreferrer">Source repository ↗</a>' : '').'</aside></div>';
    $related = array_values(array_filter($relatedProducts, fn ($p) => $p['slug'] !== $product['slug'] && $p['type'] === $product['type']));
    usort($related, fn ($a, $b) => count(array_intersect($b['categories'], $product['categories'])) <=> count(array_intersect($a['categories'], $product['categories'])));
    if ($related !== []) {
        $html .= '<section class="related-packages"><div class="section-heading"><h2>More '.strtolower($kind).'s to explore</h2><a href="/?type='.e($product['type']).'">View all →</a></div>'.grid(array_slice($related, 0, 3)).'</section>';
    }

    return $html;
}
