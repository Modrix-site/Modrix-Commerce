<?php

namespace Marketplace\Http\Controllers\Admin;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;
use Marketplace\Http\Controllers\PublishController;
use Marketplace\Models\Category;
use Marketplace\Models\License;
use Marketplace\Models\Order;
use Marketplace\Models\Package;
use Marketplace\Models\PackageRelease;
use Marketplace\Models\Submission;
use Marketplace\Services\Releases;
use Marketplace\Services\Submissions;
use Marketplace\Services\Uploads;

/** Admin overview and the package repository: listings, releases, categories, prices. */
class RepositoryController
{
    public function overview(): View
    {
        return view('admin.overview', [
            'stats' => [
                'Published packages' => Package::query()->where('status', 'published')->count(),
                'Pending submissions' => Submission::query()->where('status', 'pending')->count(),
                'Active licenses' => License::query()->where('status', 'active')->count(),
                'Paid orders' => Order::query()->where('status', 'paid')->count(),
            ],
            'recent' => Submission::query()->with('user')->latest()->limit(5)->get(),
        ]);
    }

    public function index(Request $request): View
    {
        $q = trim((string) $request->query('q', ''));

        return view('admin.repository.index', [
            'q' => $q,
            'packages' => Package::query()
                ->when($q !== '', fn ($query) => $query->where(fn ($w) => $w->where('name', 'like', "%{$q}%")->orWhere('identifier', 'like', "%{$q}%")))
                ->orderBy('name')->paginate(50)->withQueryString(),
        ]);
    }

    public function create(): View
    {
        return view('admin.repository.edit', ['package' => new Package(['status' => 'draft', 'is_free' => true, 'version' => '1.0.0', 'platform_version' => '^1.0', 'license_name' => 'MIT']), 'categories' => Category::query()->orderBy('position')->get(), 'releases' => collect()]);
    }

    public function store(Request $request, Uploads $uploads, Releases $releases): RedirectResponse
    {
        $data = $request->validate([
            'identifier' => ['required', 'string', 'max:128', 'regex:'.PublishController::IDENTIFIER, 'unique:packages,identifier'],
            'type' => ['required', 'in:theme,module'],
            'package' => ['required', 'file'],
            ...$this->listingRules(),
        ]);

        $zip = $uploads->packageZip($request->file('package'), $data['identifier'], $data['type'], $data['version']);

        $package = DB::transaction(function () use ($request, $data, $uploads, $releases, $zip) {
            $package = new Package(['identifier' => $data['identifier'], 'type' => $data['type'], 'slug' => Submissions::slugFor($data['identifier']), 'currency' => (string) config('marketplace.currency')]);
            $this->fill($request, $package, $data, $uploads);
            $releases->publish($package, $uploads->path($zip), $data['version'], $package->platform_version);

            return $package;
        });

        return redirect('/admin/repository/'.$package->slug)->with('notice', 'Package added.');
    }

    public function edit(string $slug): View
    {
        $package = Package::query()->where('slug', $slug)->with('categories')->firstOrFail();

        return view('admin.repository.edit', [
            'package' => $package,
            'categories' => Category::query()->orderBy('position')->get(),
            'releases' => $package->releases()->latest('published_at')->get(),
        ]);
    }

    public function update(Request $request, string $slug, Uploads $uploads, Releases $releases): RedirectResponse
    {
        $package = Package::query()->where('slug', $slug)->firstOrFail();
        $data = $request->validate([...$this->listingRules(), 'package' => ['nullable', 'file']]);

        // A ZIP uploaded here is published as the release for the version field.
        $zip = $request->hasFile('package') ? $uploads->packageZip($request->file('package'), $package->identifier, $package->type, $data['version']) : null;

        DB::transaction(function () use ($request, $package, $data, $uploads, $releases, $zip) {
            $this->fill($request, $package, $data, $uploads);

            if ($zip !== null) {
                $releases->publish($package, $uploads->path($zip), $data['version'], $package->platform_version);
            }
        });

        return redirect('/admin/repository/'.$package->slug)->with('notice', 'Package saved.');
    }

    /** Release history across packages. */
    public function updates(): View
    {
        return view('admin.updates', [
            'pending' => Submission::query()->where('kind', 'update')->where('status', 'pending')->with('user')->latest()->get(),
            'releases' => PackageRelease::query()->with('package')->latest('published_at')->limit(100)->get(),
        ]);
    }

    /** Prices for every package on one page. */
    public function pricing(): View
    {
        return view('admin.pricing', ['packages' => Package::query()->orderBy('name')->get()]);
    }

    public function savePricing(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'prices' => ['array'],
            'prices.*.tier' => ['required', 'in:free,premium'],
            'prices.*.price' => ['nullable', 'numeric', 'min:0', 'max:99999'],
        ]);

        foreach ($data['prices'] ?? [] as $id => $row) {
            $premium = $row['tier'] === 'premium' && (float) ($row['price'] ?? 0) > 0;
            Package::query()->whereKey((int) $id)->update(['is_free' => ! $premium, 'price' => $premium ? $row['price'] : 0]);
        }

        return redirect('/admin/catalog')->with('notice', 'Prices saved.');
    }

    /** @return array<string, list<mixed>> */
    private function listingRules(): array
    {
        return [
            'name' => ['required', 'string', 'max:160'],
            'summary' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:20000'],
            'tier' => ['required', 'in:free,premium'],
            'price' => ['required_if:tier,premium', 'nullable', 'numeric', 'min:0', 'max:99999'],
            'status' => ['required', 'in:draft,published,hidden'],
            'version' => ['required', 'string', 'max:32', 'regex:'.PublishController::VERSION],
            'platform_version' => ['nullable', 'string', 'max:64'],
            'author' => ['nullable', 'string', 'max:120'],
            'license_name' => ['nullable', 'string', 'max:64'],
            'demo_url' => ['nullable', 'url:https', 'max:500'],
            'repository_url' => ['nullable', 'url:http,https', 'max:500'],
            'changelog' => ['nullable', 'string', 'max:5000'],
            'categories' => ['array'],
            'categories.*' => ['string', 'exists:categories,slug'],
            'features_text' => ['nullable', 'string', 'max:5000'],
            'featured' => ['nullable', 'boolean'],
            'thumbnail' => ['nullable', 'file'],
        ];
    }

    /** @param  array<string, mixed>  $data */
    private function fill(Request $request, Package $package, array $data, Uploads $uploads): void
    {
        $premium = $data['tier'] === 'premium' && (float) ($data['price'] ?? 0) > 0;
        $features = array_values(array_filter(array_map('trim', preg_split('/\R/', (string) ($data['features_text'] ?? '')) ?: []), fn ($f) => $f !== ''));

        $package->fill([
            'name' => $data['name'],
            'summary' => $data['summary'],
            'description' => $data['description'] ?? $package->description,
            'is_free' => ! $premium,
            'price' => $premium ? $data['price'] : 0,
            'status' => $data['status'],
            'version' => $data['version'],
            'platform_version' => ($data['platform_version'] ?? '') ?: ($package->platform_version ?: '^1.0'),
            'author' => (string) ($data['author'] ?? $package->author ?? ''),
            'license_name' => ($data['license_name'] ?? '') ?: ($package->license_name ?: 'MIT'),
            'demo_url' => $data['demo_url'] ?? null,
            'repository_url' => $data['repository_url'] ?? null,
            'changelog' => $data['changelog'] ?? $package->changelog,
            'features' => $features,
            'featured' => $request->boolean('featured'),
        ]);

        if ($request->hasFile('thumbnail')) {
            $package->thumbnail_path = $uploads->image($request->file('thumbnail'), 'thumbnails', 'thumbnail');
        }

        $package->save();
        $package->categories()->sync(Category::query()->whereIn('slug', $data['categories'] ?? [])->pluck('id'));
    }
}
