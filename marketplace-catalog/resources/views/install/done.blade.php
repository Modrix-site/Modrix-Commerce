@extends('install.layout')

@section('content')
    <h1>Setup complete</h1>
    <p>The marketplace is installed and this setup page is now closed.</p>
    <p class="meta">Next: sign in, then add your Stripe keys, email settings and marketplace name under Settings. Set up the cron job from the deployment guide so emails go out.</p>
    <p><a class="button" href="{{ url('/admin/login') }}">Sign in to the admin</a></p>
@endsection
