<?php

namespace Marketplace\Http\Controllers\Admin;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use Marketplace\Models\Submission;
use Marketplace\Services\Submissions;
use Marketplace\Services\Uploads;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

/** Review queue for publisher submissions. */
class SubmissionController
{
    public function index(Request $request): View
    {
        $status = in_array($request->query('status'), ['approved', 'rejected', 'all'], true) ? (string) $request->query('status') : 'pending';

        return view('admin.submissions', [
            'status' => $status,
            'submissions' => Submission::query()->with('user')->when($status !== 'all', fn ($q) => $q->where('status', $status))->latest()->paginate(30)->withQueryString(),
        ]);
    }

    public function review(Request $request, Submissions $submissions): RedirectResponse
    {
        $data = $request->validate([
            'id' => ['required', 'integer'],
            'decision' => ['required', 'in:approved,rejected'],
            'review_note' => ['required_if:decision,rejected', 'nullable', 'string', 'max:2000'],
        ], ['review_note.required_if' => 'Tell the publisher why the submission was rejected.']);

        $submission = Submission::query()->findOrFail((int) $data['id']);

        if ($submission->status !== 'pending') {
            throw ValidationException::withMessages(['id' => 'This submission was already reviewed.']);
        }

        if ($data['decision'] === 'approved') {
            $package = $submissions->approve($submission, $request->user());
            $notice = $package->name.' '.$submission->data['version'].' is published.';
        } else {
            $submissions->reject($submission, $request->user(), (string) $data['review_note']);
            $notice = 'Submission rejected; the publisher can see your note.';
        }

        return redirect('/admin/submissions')->with('notice', $notice);
    }

    /** The submitted ZIP, for inspection before approval. */
    public function download(Submission $submission, Uploads $uploads): BinaryFileResponse
    {
        $file = $submission->zip_path ? $uploads->path($submission->zip_path) : null;
        abort_unless($file !== null && is_file($file), 404);

        return response()->download($file, str_replace('/', '-', $submission->identifier).'-'.($submission->data['version'] ?? 'submission').'.zip');
    }
}
