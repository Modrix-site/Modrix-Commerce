@extends('layouts.admin', ['title' => 'Updates'])

@section('admin')
    <h1>Updates</h1>
    <h2>Waiting for review</h2>
    @if ($pending->isEmpty())
        <p class="meta">No pending updates.</p>
    @else
        <ul class="plain-list">
            @foreach ($pending as $submission)
                <li>{{ $submission->identifier }} {{ $submission->data['version'] ?? '' }} · {{ $submission->user?->email }} · <a href="{{ url('/admin/submissions') }}">Review →</a></li>
            @endforeach
        </ul>
    @endif
    <h2>Published releases</h2>
    <div class="table-wrap">
        <table>
            <thead><tr><th>Package</th><th>Version</th><th>Platform</th><th>Published</th></tr></thead>
            <tbody>
            @forelse ($releases as $release)
                <tr><td><a href="{{ url('/admin/repository/'.$release->package->slug) }}">{{ $release->package->name }}</a></td><td>{{ $release->version }}</td><td>{{ $release->platform_version }}</td><td>{{ $release->published_at?->format('Y-m-d H:i') }}</td></tr>
            @empty
                <tr><td colspan="4" class="meta">No releases yet.</td></tr>
            @endforelse
            </tbody>
        </table>
    </div>
@endsection
