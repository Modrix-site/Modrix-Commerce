<?php

namespace App\Http\Controllers\Admin;

use App\Core\Marketplace\Client\MarketplaceClient;
use App\Core\Marketplace\Client\MarketplaceError;
use App\Core\Marketplace\Client\PackageInstaller;
use App\Core\Marketplace\Client\StoreLicense;
use App\Core\Marketplace\Client\StoreLicenses;
use App\Core\Marketplace\Client\UpdateHistory;
use App\Core\Marketplace\PackageException;
use App\Http\Controllers\Controller;
use App\Models\User;
use Closure;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Admin → Extensions → Marketplace on a merchant store: browse the official
 * catalog, activate licenses, install and update packages with verification,
 * and restore previous files. The page works (with a notice) when the
 * marketplace cannot be reached.
 */
class MarketplaceClientController extends Controller
{
    public function __construct(
        private readonly MarketplaceClient $client,
        private readonly StoreLicenses $licenses,
    ) {}

    public function index(): Response
    {
        $catalogError = null;

        try {
            $catalog = $this->client->catalog();
        } catch (MarketplaceError $e) {
            $catalog = [];
            $catalogError = $e->getMessage();
        }

        return Inertia::render('admin/marketplace-client/index', [
            'marketplaceUrl' => config('ecomodular.marketplace.url'),
            'trusted' => $this->client->trustedKeys() !== [],
            'catalog' => $catalog,
            'catalogError' => $catalogError,
            'licenses' => StoreLicense::query()->orderBy('product_name')->get()->map(fn (StoreLicense $l) => [
                'id' => $l->id,
                'product' => $l->product_name,
                'product_slug' => $l->product_slug,
                'extension' => $l->extension_name,
                'type' => $l->type,
                'key_hint' => $l->key_hint,
                'status' => $l->status,
                'updates_until' => $l->updates_until?->toDateString(),
                'installed_version' => $this->licenses->installedVersion($l),
                'available' => $l->available_release === null ? null : [
                    'version' => $l->available_release['version'] ?? null,
                    'changelog' => $l->available_release['changelog'] ?? null,
                ],
                'last_checked_at' => $l->last_checked_at?->toIso8601String(),
                'last_error' => $l->last_error,
                'needs_verification' => $l->needsVerification(),
            ]),
            'history' => UpdateHistory::query()->with('actor:id,email')->latest('id')->limit(30)->get()->map(fn (UpdateHistory $h) => [
                'id' => $h->id,
                'extension' => $h->extension_name,
                'action' => $h->action,
                'from_version' => $h->from_version,
                'to_version' => $h->to_version,
                'success' => $h->success,
                'message' => $h->message,
                'can_rollback' => $h->success && $h->backup_path !== null && is_file($h->backup_path),
                'actor' => $h->actor?->email,
                'created_at' => $h->created_at->toIso8601String(),
            ]),
        ]);
    }

    public function activate(Request $request): RedirectResponse
    {
        $data = $request->validate(['key' => ['required', 'string', 'max:64', 'regex:/^\s*EMK(-[A-Za-z0-9]{5}){4}\s*$/']], ['key.regex' => 'Enter a license key like EMK-XXXXX-XXXXX-XXXXX-XXXXX.']);

        return $this->run(fn () => $this->licenses->activate($data['key'], $this->actor($request)), 'License activated on this store.', 'key');
    }

    public function check(StoreLicense $license): RedirectResponse
    {
        $license = $this->licenses->check($license);
        Inertia::flash('toast', $license->last_error === null
            ? ['type' => 'success', 'message' => 'License checked.']
            : ['type' => 'error', 'message' => $license->last_error]);

        return back();
    }

    public function deactivate(Request $request, StoreLicense $license): RedirectResponse
    {
        return $this->run(fn () => $this->licenses->deactivate($license, $this->actor($request)), 'License removed from this store. Installed files were not changed.');
    }

    public function install(Request $request, StoreLicense $license, PackageInstaller $installer): RedirectResponse
    {
        return $this->run(function () use ($request, $license, $installer) {
            $entry = $installer->install($license, $this->actor($request));
            Inertia::flash('toast', ['type' => 'success', 'message' => "Version {$entry->to_version} is in place and verified. Go to Extensions or Appearance to install, update or enable it."]);
        }, '');
    }

    public function rollback(Request $request, UpdateHistory $entry, PackageInstaller $installer): RedirectResponse
    {
        return $this->run(fn () => $installer->rollback($entry, $this->actor($request)), 'Previous files restored.');
    }

    /**
     * @param  Closure(): mixed  $operation
     */
    private function run(Closure $operation, string $success, string $field = 'marketplace'): RedirectResponse
    {
        try {
            $operation();
        } catch (MarketplaceError|PackageException $e) {
            Inertia::flash('toast', ['type' => 'error', 'message' => $e->getMessage()]);

            throw ValidationException::withMessages([$field => $e->getMessage()]);
        }

        if ($success !== '') {
            Inertia::flash('toast', ['type' => 'success', 'message' => $success]);
        }

        return redirect()->route('admin.marketplace-client.index');
    }

    private function actor(Request $request): User
    {
        /** @var User */
        return $request->user();
    }
}
