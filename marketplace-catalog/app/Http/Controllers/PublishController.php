<?php

namespace Marketplace\Http\Controllers;

use Composer\Semver\Comparator;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use Marketplace\Models\Package;
use Marketplace\Models\Submission;
use Marketplace\Services\Uploads;

/** Publishers send new packages and updates for review. */
class PublishController
{
    public const IDENTIFIER = '/^[a-z0-9][a-z0-9-]*\/[a-z0-9][a-z0-9-]*$/';

    public const VERSION = '/^\d+\.\d+\.\d+(-[0-9A-Za-z.-]+)?$/';

    public function create(): View
    {
        return view('publish.create');
    }

    public function store(Request $request, Uploads $uploads): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:160'],
            'identifier' => ['required', 'string', 'max:128', 'regex:'.self::IDENTIFIER, 'unique:packages,identifier'],
            'type' => ['required', 'in:theme,module'],
            'version' => ['required', 'string', 'max:32', 'regex:'.self::VERSION],
            'platform_version' => ['required', 'string', 'max:64'],
            'author' => ['required', 'string', 'max:120'],
            'license_name' => ['required', 'string', 'max:64'],
            'summary' => ['required', 'string', 'max:255'],
            'tier' => ['required', 'in:free,premium'],
            'price' => ['required_if:tier,premium', 'nullable', 'numeric', 'min:0', 'max:99999'],
            'demo_url' => ['nullable', 'url:https', 'max:500'],
            'package' => ['required', 'file'],
        ], ['demo_url.url' => 'The demo URL must start with https://.']);

        if ($data['tier'] === 'premium' && (float) $data['price'] <= 0) {
            throw ValidationException::withMessages(['price' => 'Set a price above zero for a paid package.']);
        }

        if (Submission::query()->where('identifier', $data['identifier'])->where('status', 'pending')->exists()) {
            throw ValidationException::withMessages(['identifier' => 'This package is already waiting for review.']);
        }

        $zip = $uploads->packageZip($request->file('package'), $data['identifier'], $data['type'], $data['version']);
        unset($data['package']);

        Submission::query()->create(['user_id' => $request->user()->id, 'identifier' => $data['identifier'], 'kind' => 'new', 'data' => $data, 'zip_path' => $zip]);

        return redirect('/submissions')->with('notice', 'Thanks! Your package is waiting for review.');
    }

    public function edit(Request $request): View
    {
        return view('publish.update', [
            'packages' => Package::query()->where('publisher_id', $request->user()->id)->orderBy('name')->get(),
        ]);
    }

    public function update(Request $request, Uploads $uploads): RedirectResponse
    {
        $data = $request->validate([
            'identifier' => ['required', 'string', 'max:128'],
            'version' => ['required', 'string', 'max:32', 'regex:'.self::VERSION],
            'platform_version' => ['nullable', 'string', 'max:64'],
            'changelog' => ['required', 'string', 'max:5000'],
            'package' => ['required', 'file'],
        ]);

        $package = Package::query()->where('identifier', $data['identifier'])->first();

        // Packages without a publisher were added by the marketplace; anyone may propose an update for review.
        if ($package === null || ($package->publisher_id !== null && $package->publisher_id !== $request->user()->id)) {
            throw ValidationException::withMessages(['identifier' => 'Choose one of your published packages.']);
        }

        $current = $package->latestRelease()?->version ?? $package->version;

        if (! Comparator::greaterThan($data['version'], $current)) {
            throw ValidationException::withMessages(['version' => "The new version must be higher than {$current}."]);
        }

        $zip = $uploads->packageZip($request->file('package'), $package->identifier, $package->type, $data['version']);
        unset($data['package']);
        $data['platform_version'] = ($data['platform_version'] ?? '') ?: $package->platform_version;

        Submission::query()->create(['user_id' => $request->user()->id, 'identifier' => $package->identifier, 'kind' => 'update', 'data' => $data, 'zip_path' => $zip]);

        return redirect('/submissions')->with('notice', 'Thanks! Your update is waiting for review.');
    }

    public function index(Request $request): View
    {
        return view('publish.index', [
            'submissions' => Submission::query()->where('user_id', $request->user()->id)->latest()->get(),
        ]);
    }
}
