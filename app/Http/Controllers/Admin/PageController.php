<?php

namespace App\Http\Controllers\Admin;

use App\Core\Audit\AuditLogger;
use App\Domains\Content\Models\Page;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class PageController extends Controller
{
    public function index(): Response
    {
        return Inertia::render('admin/pages/index', ['pages' => Page::withTrashed()->orderBy('title')->get()]);
    }

    public function create(): Response
    {
        return Inertia::render('admin/pages/edit', ['page' => null]);
    }

    public function edit(Page $page): Response
    {
        return Inertia::render('admin/pages/edit', ['page' => $page]);
    }

    public function store(Request $request, AuditLogger $audit): RedirectResponse
    {
        $page = Page::create($this->validated($request));
        $audit->record('page.created', $page);

        return to_route('admin.pages.edit', $page);
    }

    public function update(Request $request, Page $page, AuditLogger $audit): RedirectResponse
    {
        $page->update($this->validated($request, $page));
        $audit->record('page.updated', $page);
        Inertia::flash('toast', ['type' => 'success', 'message' => 'Page saved.']);

        return back();
    }

    public function destroy(Page $page, AuditLogger $audit): RedirectResponse
    {
        $audit->record('page.deleted', $page);
        $page->delete();

        return to_route('admin.pages.index');
    }

    public function restore(int $page, AuditLogger $audit): RedirectResponse
    {
        $record = Page::onlyTrashed()->findOrFail($page);
        $record->restore();
        $audit->record('page.restored', $record);

        return back();
    }

    /** @return array<string, mixed> */
    private function validated(Request $request, ?Page $page = null): array
    {
        return $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'slug' => ['required', 'string', 'max:255', 'regex:/^[a-z0-9]+(?:-[a-z0-9]+)*$/', Rule::unique('pages')->ignore($page?->id)],
            'content' => ['nullable', 'string', 'max:100000'], 'status' => ['required', Rule::in(['draft', 'published'])],
            'meta_title' => ['nullable', 'string', 'max:255'], 'meta_description' => ['nullable', 'string', 'max:500'],
        ]);
    }
}
