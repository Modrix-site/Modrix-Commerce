@extends('layouts.admin', ['title' => 'Submissions'])

@section('admin')
    <h1>Submissions</h1>
    <nav class="tabs" aria-label="Submission status">
        @foreach (['pending' => 'Pending', 'approved' => 'Approved', 'rejected' => 'Rejected', 'all' => 'All'] as $value => $label)
            <a href="{{ url('/admin/submissions?status='.$value) }}" @if ($status === $value) aria-current="page" @endif>{{ $label }}</a>
        @endforeach
    </nav>
    @forelse ($submissions as $submission)
        @php($d = $submission->data)
        <article class="panel submission">
            <header class="admin-heading">
                <div>
                    <h2>{{ $d['name'] ?? $submission->identifier }} <span class="meta">{{ $d['version'] ?? '' }}</span></h2>
                    <p class="meta">{{ $submission->kind === 'update' ? 'Update to' : 'New' }} {{ $submission->identifier }} · by {{ $submission->user?->email }} · {{ $submission->created_at->format('Y-m-d H:i') }}</p>
                </div>
                <span class="status status-{{ $submission->status }}">{{ ucfirst($submission->status) }}</span>
            </header>
            <dl class="package-specs">
                @if ($submission->kind === 'update')
                    <div><dt>Changes</dt><dd>{!! nl2br(e($d['changelog'] ?? '')) !!}</dd></div>
                @else
                    <div><dt>Type</dt><dd>{{ ($d['type'] ?? '') === 'theme' ? 'Theme' : 'Extension' }}</dd></div>
                    <div><dt>Summary</dt><dd>{{ $d['summary'] ?? '' }}</dd></div>
                    <div><dt>Price</dt><dd>{{ ($d['tier'] ?? 'free') === 'premium' ? config('marketplace.currency').' '.($d['price'] ?? '') : 'Free' }}</dd></div>
                    <div><dt>Author / license</dt><dd>{{ $d['author'] ?? '' }} · {{ $d['license_name'] ?? '' }}</dd></div>
                    @if (! empty($d['demo_url']))<div><dt>Demo</dt><dd><a href="{{ $d['demo_url'] }}" target="_blank" rel="noopener noreferrer">{{ $d['demo_url'] }}</a></dd></div>@endif
                @endif
                <div><dt>Platform</dt><dd>{{ $d['platform_version'] ?? '' }}</dd></div>
            </dl>
            <p><a href="{{ url('/admin/submissions/'.$submission->id.'/download') }}">Download the ZIP to inspect ↓</a></p>
            @if ($submission->status === 'pending')
                <form method="post" action="{{ url('/admin/submissions') }}" class="stack">
                    @csrf
                    <input type="hidden" name="id" value="{{ $submission->id }}">
                    <label>Note to the publisher (required when rejecting) <textarea name="review_note" rows="2" maxlength="2000"></textarea></label>
                    <div><button type="submit" name="decision" value="approved">Approve and publish</button> <button class="secondary" type="submit" name="decision" value="rejected">Reject</button></div>
                </form>
            @elseif ($submission->review_note)
                <p class="meta">Note: {{ $submission->review_note }}</p>
            @endif
        </article>
    @empty
        <p class="meta">No submissions here.</p>
    @endforelse
    {{ $submissions->links() }}
@endsection
