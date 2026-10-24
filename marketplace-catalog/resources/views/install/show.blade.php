@extends('install.layout')

@section('content')
    <ol class="install-steps">
        <li @if ($step === 'database') aria-current="step" @endif>Database</li>
        <li @if ($step === 'admin') aria-current="step" @endif>Admin account</li>
    </ol>

    @if ($step === 'database')
        <h1>Connect MySQL</h1>
        <p class="meta">Create a MySQL (5.7+) or MariaDB (10.3+) database and user in your hosting control panel, then enter them here. The installer saves them in <code>.env</code> and creates the tables.</p>
        <ul class="requirements">
            @foreach ($requirements as $label => $ok)
                <li class="{{ $ok ? 'ok' : 'missing' }}">{{ $ok ? '✓' : '✗' }} {{ $label }}</li>
            @endforeach
        </ul>
        <form method="post" action="{{ url('/install/database') }}" class="stack">
            @csrf
            <label>Database server (host) <input name="host" value="{{ old('host', 'localhost') }}" required maxlength="255"></label>
            <label>Port <input name="port" type="number" min="1" max="65535" value="{{ old('port', '3306') }}" required></label>
            <label>Database name <input name="database" value="{{ old('database') }}" required maxlength="64" pattern="[A-Za-z0-9_$\-]+"></label>
            <label>Database user <input name="username" value="{{ old('username') }}" required maxlength="80" autocomplete="off"></label>
            <label>Database password <input type="password" name="password" maxlength="255" autocomplete="new-password"></label>
            <button type="submit">Test connection and continue</button>
        </form>
    @elseif ($hasAdmin)
        <h1>Admin account</h1>
        <p>The database already has an administrator (for example from an import). Finish setup and sign in with that account.</p>
        <form method="post" action="{{ url('/install/admin') }}">@csrf<button type="submit">Finish setup</button></form>
    @else
        <h1>Admin account</h1>
        <p class="meta">The database is ready. Create the administrator who manages the marketplace.</p>
        <form method="post" action="{{ url('/install/admin') }}" class="stack">
            @csrf
            <label>Name <input name="name" value="{{ old('name') }}" required maxlength="120" autocomplete="name"></label>
            <label>Email <input type="email" name="email" value="{{ old('email') }}" required autocomplete="email"></label>
            <label>Password (12+ characters) <input type="password" name="password" required minlength="12" autocomplete="new-password"></label>
            <label>Confirm password <input type="password" name="password_confirmation" required minlength="12" autocomplete="new-password"></label>
            <button type="submit">Finish setup</button>
        </form>
    @endif
@endsection
