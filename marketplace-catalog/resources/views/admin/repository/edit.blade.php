@php($new = ! $package->exists)
@extends('layouts.admin', ['title' => $new ? 'Add a package' : $package->name])

@section('admin')
    <div class="admin-heading">
        <h1>{{ $new ? 'Add a package' : $package->name }}</h1>
        @if (! $new && $package->status === 'published')
            <a href="{{ url('/item/'.$package->slug) }}" target="_blank" rel="noopener">View listing ↗</a>
        @endif
    </div>
    <form method="post" action="{{ url($new ? '/admin/repository/new' : '/admin/repository/'.$package->slug) }}" enctype="multipart/form-data" class="form-grid">
        @csrf
        @if ($new)
            <label>Package identifier <input name="identifier" value="{{ old('identifier') }}" required placeholder="vendor/package"></label>
            <label>Type
                <select name="type">
                    <option value="module" @selected(old('type') !== 'theme')>Extension</option>
                    <option value="theme" @selected(old('type') === 'theme')>Theme</option>
                </select>
            </label>
        @else
            <p class="wide meta">{{ $package->identifier }} · {{ $package->type === 'theme' ? 'Theme' : 'Extension' }}</p>
        @endif
        <label>Name <input name="name" value="{{ old('name', $package->name) }}" required maxlength="160"></label>
        <label>Status
            <select name="status">
                @foreach (['draft' => 'Draft', 'published' => 'Published', 'hidden' => 'Hidden'] as $value => $label)
                    <option value="{{ $value }}" @selected(old('status', $package->status) === $value)>{{ $label }}</option>
                @endforeach
            </select>
        </label>
        <label class="wide">Summary <textarea name="summary" required maxlength="255" rows="2">{{ old('summary', $package->summary) }}</textarea></label>
        <label>Price
            <select name="tier">
                <option value="free" @selected(old('tier', $package->is_free ? 'free' : 'premium') === 'free')>Free</option>
                <option value="premium" @selected(old('tier', $package->is_free ? 'free' : 'premium') === 'premium')>Paid</option>
            </select>
        </label>
        <label>Price ({{ app(\Marketplace\Services\Catalog::class)->currency() }}) <input name="price" type="number" min="0" step="0.01" value="{{ old('price', $package->price ?? '0') }}"></label>
        <label>Version <input name="version" value="{{ old('version', $package->version) }}" required></label>
        <label>Platform version <input name="platform_version" value="{{ old('platform_version', $package->platform_version) }}"></label>
        <label>Author <input name="author" value="{{ old('author', $package->author) }}" maxlength="120"></label>
        <label>License <input name="license_name" value="{{ old('license_name', $package->license_name) }}" maxlength="64"></label>
        <label>Live demo URL <input name="demo_url" type="url" value="{{ old('demo_url', $package->demo_url) }}" placeholder="https://"></label>
        <label>Source repository URL <input name="repository_url" type="url" value="{{ old('repository_url', $package->repository_url) }}"></label>
        <fieldset class="wide">
            <legend>Categories</legend>
            @php($chosen = old('categories', $package->exists ? $package->categories->pluck('slug')->all() : []))
            @forelse ($categories as $category)
                <label class="check"><input type="checkbox" name="categories[]" value="{{ $category->slug }}" @checked(in_array($category->slug, $chosen, true))> {{ $category->name }}</label>
            @empty
                <span class="meta">No categories yet. <a href="{{ url('/admin/categories') }}">Add one</a>.</span>
            @endforelse
        </fieldset>
        <label class="wide">Key features (one per line) <textarea name="features_text" rows="4">{{ old('features_text', implode("\n", $package->features ?? [])) }}</textarea></label>
        <label class="wide">Latest release notes <textarea name="changelog" rows="3">{{ old('changelog', $package->changelog) }}</textarea></label>
        <label class="check wide"><input type="checkbox" name="featured" value="1" @checked(old('featured', $package->featured))> Featured on the home page</label>
        <label>Thumbnail (PNG, JPEG or WebP) <input type="file" name="thumbnail" accept="image/png,image/jpeg,image/webp"></label>
        <label>{{ $new ? 'Package ZIP' : 'Publish a release ZIP (optional, for the version above)' }} <input type="file" name="package" accept=".zip,application/zip" @required($new)></label>
        <div class="wide"><button type="submit">{{ $new ? 'Add package' : 'Save package' }}</button></div>
    </form>

    @if ($releases->isNotEmpty())
        <h2>Releases</h2>
        <div class="table-wrap">
            <table>
                <thead><tr><th>Version</th><th>Platform</th><th>Size</th><th>SHA-256</th><th>Published</th></tr></thead>
                <tbody>
                @foreach ($releases as $release)
                    <tr><td>{{ $release->version }}</td><td>{{ $release->platform_version }}</td><td>{{ number_format($release->size / 1024, 1) }} KB</td><td><code>{{ substr($release->sha256, 0, 16) }}…</code></td><td>{{ $release->published_at?->format('Y-m-d H:i') }}</td></tr>
                @endforeach
                </tbody>
            </table>
        </div>
    @endif
@endsection
