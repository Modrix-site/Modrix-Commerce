@if (! empty($product['thumbnail']))
    <img src="{{ $product['thumbnail'] }}" alt="{{ $product['name'] }} preview" loading="lazy">
@else
    @php($theme = $product['type'] === 'theme')
    <span class="swatch {{ $theme ? 'theme-preview' : 'extension-preview' }}" style="--preview-hue:{{ hexdec(substr(md5($product['slug']), 0, 2)) % 360 }}">
        <span class="preview-symbol" aria-hidden="true">{{ $theme ? '▦' : '✦' }}</span>
        <span class="preview-name">{{ $product['name'] }}</span>
        <span class="preview-label">{{ $theme ? 'STOREFRONT THEME' : 'ECOMODULAR EXTENSION' }}</span>
    </span>
@endif
