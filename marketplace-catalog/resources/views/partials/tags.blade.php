<div class="package-tags">
    @foreach ($product['category_labels'] as $category)
        <a href="{{ url('/?category='.rawurlencode($category['slug'])) }}">{{ $category['name'] }}</a>
    @endforeach
</div>
