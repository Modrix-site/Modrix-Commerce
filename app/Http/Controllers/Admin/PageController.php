<?php

namespace App\Http\Controllers\Admin;

use App\Core\Audit\AuditLogger;
use App\Core\Content\RichText;
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
        return Inertia::render('admin/pages/index', [
            'pages' => Page::withTrashed()->orderBy('title')->get(['id', 'title', 'slug', 'kind', 'status', 'updated_at', 'deleted_at']),
        ]);
    }

    public function create(Request $request): Response
    {
        return Inertia::render('admin/pages/edit', [
            'page' => null,
            'kind' => $request->query('kind') === Page::KIND_FAQ ? Page::KIND_FAQ : Page::KIND_PAGE,
        ]);
    }

    public function edit(Page $page): Response
    {
        return Inertia::render('admin/pages/edit', ['page' => $page]);
    }

    public function store(Request $request, AuditLogger $audit): RedirectResponse
    {
        $page = Page::create($this->validated($request));
        $audit->record('page.created', $page);
        Inertia::flash('toast', ['type' => 'success', 'message' => 'Page created.']);

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

    public function duplicate(Page $page, AuditLogger $audit): RedirectResponse
    {
        $copy = $page->replicate();
        $copy->title = mb_substr($page->title.' copy', 0, 255);
        $copy->slug = $this->uniqueSlug($page->slug);
        $copy->status = 'draft';
        $copy->save();
        $audit->record('page.duplicated', $copy);
        Inertia::flash('toast', ['type' => 'success', 'message' => 'Page duplicated. It is saved as a draft.']);

        return to_route('admin.pages.edit', $copy);
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
        $data = $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'slug' => ['required', 'string', 'max:255', 'regex:/^[a-z0-9]+(?:-[a-z0-9]+)*$/', Rule::unique('pages')->ignore($page?->id)],
            'kind' => ['nullable', Rule::in([Page::KIND_PAGE, Page::KIND_FAQ])],
            'content' => ['nullable', 'string', 'max:60000'],
            'faqs' => ['array', 'max:50'],
            'faqs.*.question' => ['nullable', 'string', 'max:300'],
            'faqs.*.answer' => ['nullable', 'string', 'max:10000'],
            'status' => ['required', Rule::in(['draft', 'published'])],
            'meta_title' => ['nullable', 'string', 'max:255'],
            'meta_description' => ['nullable', 'string', 'max:500'],
        ]);
        $data['content'] = RichText::clean($data['content'] ?? null);
        $data['kind'] = ($data['kind'] ?? Page::KIND_PAGE) === Page::KIND_FAQ ? Page::KIND_FAQ : Page::KIND_PAGE;
        $faqs = [];
        foreach ($data['faqs'] ?? [] as $item) {
            if (! is_array($item)) {
                continue;
            }
            $question = trim((string) ($item['question'] ?? ''));
            $answer = RichText::clean(is_string($item['answer'] ?? null) ? $item['answer'] : null);
            if ($question === '') {
                continue;
            }
            $faqs[] = ['question' => $question, 'answer' => $answer];
        }
        $data['faqs'] = $faqs;

        return $data;
    }

    private function uniqueSlug(string $slug): string
    {
        $base = mb_substr(rtrim($slug, '-'), 0, 240).'-copy';
        $candidate = $base;
        $suffix = 2;
        while (Page::withTrashed()->where('slug', $candidate)->exists()) {
            $candidate = mb_substr($base, 0, 240).'-'.$suffix;
            $suffix++;
        }

        return $candidate;
    }
}
