@extends('layouts.admin', ['title' => 'Admin overview'])

@section('admin')
    <h1>Overview</h1>
    <div class="stat-grid">
        @foreach ($stats as $label => $value)
            <div class="stat"><span>{{ $label }}</span><strong>{{ $value }}</strong></div>
        @endforeach
    </div>
    <div class="quick-links">
        <a class="button" href="{{ url('/admin/repository') }}">Repository</a>
        <a class="button secondary" href="{{ url('/admin/repository/new') }}">Add a package</a>
        <a class="button secondary" href="{{ url('/admin/submissions') }}">Review submissions</a>
        <a class="button secondary" href="{{ url('/admin/general?tab=payments') }}">Stripe settings</a>
    </div>
    <h2>Recent submissions</h2>
    @if ($recent->isEmpty())
        <p class="meta">No submissions yet.</p>
    @else
        <div class="table-wrap">
            <table>
                <thead><tr><th>Date</th><th>Package</th><th>Publisher</th><th>Status</th></tr></thead>
                <tbody>
                @foreach ($recent as $submission)
                    <tr><td>{{ $submission->created_at->format('Y-m-d') }}</td><td>{{ $submission->identifier }} {{ $submission->data['version'] ?? '' }}</td><td>{{ $submission->user?->email }}</td><td>{{ ucfirst($submission->status) }}</td></tr>
                @endforeach
                </tbody>
            </table>
        </div>
    @endif
@endsection
