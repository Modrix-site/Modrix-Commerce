@extends('layouts.admin', ['title' => 'Categories'])

@section('admin')
    <h1>Categories</h1>
    @foreach ($categories as $category)
        <form method="post" action="{{ url('/admin/categories') }}" class="panel inline-form category-row">
            @csrf
            <input type="hidden" name="id" value="{{ $category->id }}">
            <code>{{ $category->slug }}</code>
            <label>Name <input name="name" value="{{ $category->name }}" required maxlength="120"></label>
            <label>Shown for
                <select name="type">
                    @foreach (['all' => 'All', 'theme' => 'Themes', 'module' => 'Extensions'] as $value => $label)
                        <option value="{{ $value }}" @selected($category->type === $value)>{{ $label }}</option>
                    @endforeach
                </select>
            </label>
            <label>Order <input name="position" type="number" min="0" value="{{ $category->position }}"></label>
            <label class="check"><input type="checkbox" name="enabled" value="1" @checked($category->enabled)> Enabled</label>
            <input type="hidden" name="description" value="{{ $category->description }}">
            <span class="meta">{{ $category->packages_count }} packages</span>
            <button class="secondary" type="submit">Save</button>
        </form>
    @endforeach

    <h2>Add a category</h2>
    <form method="post" action="{{ url('/admin/categories') }}" class="form-grid">
        @csrf
        <label>Slug (fixed once created) <input name="slug" value="{{ old('slug') }}" required pattern="[a-z0-9][a-z0-9\-]*" maxlength="64"></label>
        <label>Name <input name="name" value="{{ old('name') }}" required maxlength="120"></label>
        <label>Shown for
            <select name="type"><option value="all">All</option><option value="theme">Themes</option><option value="module">Extensions</option></select>
        </label>
        <label>Order <input name="position" type="number" min="0" value="0"></label>
        <label class="wide">Description <input name="description" maxlength="255" value="{{ old('description') }}"></label>
        <label class="check"><input type="checkbox" name="enabled" value="1" checked> Enabled</label>
        <div class="wide"><button type="submit">Add category</button></div>
    </form>
@endsection
