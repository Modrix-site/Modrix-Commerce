@extends('layouts.site', ['bare' => true, 'bodyClass' => 'marketplace-body admin-body'])

@section('content')
    <div class="admin-layout">
        <nav class="admin-nav" aria-label="Admin">
            @foreach ([
                'admin' => 'Overview',
                'admin/repository' => 'Repository',
                'admin/submissions' => 'Submissions',
                'admin/updates' => 'Updates',
                'admin/categories' => 'Categories',
                'admin/catalog' => 'Pricing',
                'admin/licenses' => 'Licenses',
                'admin/orders' => 'Orders',
                'admin/general' => 'Settings',
            ] as $path => $label)
                <a href="{{ url('/'.$path) }}" @if (request()->is($path) || ($path !== 'admin' && request()->is($path.'/*'))) aria-current="page" @endif>{{ $label }}</a>
            @endforeach
            <form method="post" action="{{ url('/admin/logout') }}">@csrf<button class="link-button" type="submit">Sign out</button></form>
        </nav>
        <section class="admin-main">
            @yield('admin')
        </section>
    </div>
@endsection
