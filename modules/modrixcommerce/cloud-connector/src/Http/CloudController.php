<?php

namespace EcoModular\CloudConnector\Http;

use App\Core\Extensions\ModuleRepository;
use App\Core\Marketplace\Client\StoreLicense;
use App\Core\Marketplace\Client\UpdateHistory;
use App\Core\Marketplace\PackageException;
use App\Core\Settings\StoreSettings;
use App\Core\Themes\ThemeRepository;
use App\Http\Controllers\Controller;
use App\Models\User;
use Closure;
use Composer\Semver\Comparator;
use EcoModular\CloudConnector\CloudClient;
use EcoModular\CloudConnector\CloudError;
use EcoModular\CloudConnector\PackageInstaller;
use EcoModular\CloudConnector\StoreLicenses;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\Response as SymfonyResponse;

/**
 * Admin → Modrix Cloud: connect the store to a Modrix Cloud account, browse
 * every published theme and extension, install free and owned ones in one
 * click, activate license keys, update with verification and restore
 * previous files. The page works (with a notice) when Modrix Cloud cannot be
 * reached.
 */
class CloudController extends Controller
{
    /** Session key of the pending connection (state and PKCE verifier); never sent to the browser. */
    private const PAIRING = 'cloud_connector.pairing';

    public function __construct(
        private readonly CloudClient $client,
        private readonly StoreLicenses $licenses,
    ) {}

    public function index(): Response
    {
        $catalogError = null;

        try {
            $catalog = $this->client->catalog();
        } catch (CloudError $e) {
            $catalog = [];
            $catalogError = $e->getMessage();
        }

        $local = StoreLicense::query()->orderBy('product_name')->get();
        $licensed = $local->pluck('id', 'extension_name');

        $route = fn (string $name, mixed $parameters = []) => route("admin.modrixcommerce.cloud-connector.{$name}", $parameters);

        return Inertia::render('module/modrixcommerce/cloud-connector/index', [
            'cloudUrl' => $this->client->baseUrl(),
            'urls' => [
                'index' => $route('index'),
                'connect' => $route('connect'),
                'disconnect' => $route('disconnect'),
                'sync' => $route('licenses.sync'),
                'activate' => $route('licenses.activate'),
                'install' => $route('install-from-cloud'),
                'checkUpdates' => $route('updates.check'),
                'appearance' => route('admin.themes.index'),
                'extensions' => route('admin.extensions.index'),
            ],
            'trusted' => $this->client->trustedKeys() !== [],
            'connection' => $this->client->account(),
            'catalog' => array_map(fn (array $item) => $this->catalogItem($item, $licensed->all()), $catalog),
            'catalogError' => $catalogError,
            'licenses' => $local->map(fn (StoreLicense $l) => [
                'id' => $l->id,
                'urls' => [
                    'check' => $route('licenses.check', $l),
                    'deactivate' => $route('licenses.deactivate', $l),
                    'install' => $route('install', $l),
                ],
                'product' => $l->product_name,
                'product_slug' => $l->product_slug,
                'extension' => $l->extension_name,
                'type' => $l->type,
                'key_hint' => $l->key_hint,
                'status' => $l->status,
                'updates_until' => $l->updates_until?->toDateString(),
                'installed_version' => $l->installedVersion(),
                'available' => $l->available_release === null ? null : [
                    'version' => $l->available_release['version'] ?? null,
                    'changelog' => $l->available_release['changelog'] ?? null,
                ],
                'last_checked_at' => $l->last_checked_at?->toIso8601String(),
                'last_error' => $l->last_error,
                'needs_verification' => $l->needsVerification(),
                // Only installed packages can be affected.
                'advisories' => $l->installedVersion() === null ? [] : $l->advisoriesFor($l->installedVersion()),
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
                'rollback_url' => $route('rollback', $h),
                'actor' => $h->actor?->email,
                'created_at' => $h->created_at->toIso8601String(),
            ]),
        ]);
    }

    /**
     * Starts connecting: the browser goes to Modrix Cloud to approve this store.
     */
    public function connect(Request $request, StoreSettings $store): SymfonyResponse
    {
        $state = Str::random(40);
        $verifier = Str::random(64);
        $request->session()->put(self::PAIRING, ['state' => $state, 'verifier' => $verifier, 'expires' => now()->addMinutes(10)->getTimestamp()]);

        return Inertia::location($this->client->authorizeUrl($state, $verifier, route('admin.modrixcommerce.cloud-connector.callback'), $store->name()));
    }

    /**
     * Modrix Cloud sends the browser back here with a one-time code.
     */
    public function callback(Request $request, StoreLicenses $licenses): RedirectResponse
    {
        $pairing = $request->session()->pull(self::PAIRING);
        $state = (string) $request->query('state', '');

        if (! is_array($pairing) || ! hash_equals((string) ($pairing['state'] ?? ''), $state) || (int) ($pairing['expires'] ?? 0) < now()->getTimestamp()) {
            return $this->back('error', 'This connection request expired or did not start here. Choose Connect again.');
        }

        if ($request->query('error') === 'access_denied') {
            return $this->back('error', 'Connecting was cancelled on Modrix Cloud.');
        }

        $code = $request->query('code');

        if (! is_string($code) || $code === '' || strlen($code) > 128) {
            return $this->back('error', 'Modrix Cloud did not send a connection code. Choose Connect again.');
        }

        try {
            $this->client->connect($code, (string) $pairing['verifier']);
            $synced = $licenses->sync($this->actor($request));
        } catch (CloudError $e) {
            return $this->back('error', $e->getMessage());
        }

        $account = $this->client->account();
        $message = 'Connected to Modrix Cloud'.(isset($account['email']) ? " as {$account['email']}" : '').'.';
        $message .= $synced['added'] === [] ? '' : ' Added licenses: '.implode(', ', $synced['added']).'.';
        $message .= $synced['elsewhere'] === [] ? '' : ' Active on another store (use Move here): '.implode(', ', $synced['elsewhere']).'.';

        return $this->back('success', $message);
    }

    public function disconnect(): RedirectResponse
    {
        $this->client->disconnect();

        return $this->back('success', 'Disconnected from Modrix Cloud. Licenses already on this store keep working.');
    }

    /** Adds the connected account's licenses that are not on this store yet. */
    public function sync(Request $request): RedirectResponse
    {
        return $this->run(function () use ($request) {
            $synced = $this->licenses->sync($this->actor($request));
            $this->client->forgetCatalog();
            Inertia::flash('toast', ['type' => 'success', 'message' => $synced['added'] === []
                ? 'Licenses are up to date.'.($synced['elsewhere'] === [] ? '' : ' Active on another store: '.implode(', ', $synced['elsewhere']).'.')
                : 'Added licenses: '.implode(', ', $synced['added']).'.']);
        }, '');
    }

    /**
     * One-click install of a free or owned catalog item: license from the
     * account, then the verified installer.
     */
    public function installFromCloud(Request $request, PackageInstaller $installer): RedirectResponse
    {
        $data = $request->validate([
            'identifier' => ['required', 'string', 'max:128', 'regex:/^[a-z0-9-]+\/[a-z0-9-]+$/'],
            'move_here' => ['sometimes', 'boolean'],
        ]);

        return $this->run(function () use ($request, $data, $installer) {
            $before = $this->diskVersion($data['identifier']);
            // Activating also pulls a newer signed release when one is published.
            $license = $this->licenses->activateFromAccount($data['identifier'], $this->actor($request), (bool) ($data['move_here'] ?? false));
            $this->client->forgetCatalog();
            $where = $license->type === 'theme' ? 'Appearance' : 'Extensions';

            if ($license->installedVersion() === null) {
                $entry = $installer->install($license, $this->actor($request));
                Inertia::flash('toast', ['type' => 'success', 'message' => "{$license->product_name} {$entry->to_version} is in place and verified. Go to {$where} to install and enable it."]);
            } elseif ($before !== null && $license->installedVersion() !== $before) {
                Inertia::flash('toast', ['type' => 'success', 'message' => "{$license->product_name} was updated to v{$license->installedVersion()} and verified. Apply the update in {$where}."]);
            } else {
                Inertia::flash('toast', ['type' => 'success', 'message' => "{$license->product_name} is licensed on this store and up to date."]);
            }
        }, '', 'identifier');
    }

    /**
     * Check for updates: every license on this store is checked, and newer
     * signed releases are downloaded, verified and put in place.
     */
    public function checkUpdates(Request $request): RedirectResponse
    {
        $result = $this->licenses->checkAll($this->actor($request));
        $this->client->forgetCatalog();

        $message = $result['updated'] === []
            ? 'Everything licensed on this store is up to date.'
            : 'Updated and verified: '.implode(', ', $result['updated']).'. Apply the updates in Extensions or Appearance.';

        if ($result['failed'] !== []) {
            $message .= ' Could not check: '.implode(', ', $result['failed']).' (see Licenses below).';
        }

        Inertia::flash('toast', ['type' => $result['failed'] === [] ? 'success' : 'error', 'message' => $message]);

        return redirect()->route('admin.modrixcommerce.cloud-connector.index');
    }

    public function activate(Request $request): RedirectResponse
    {
        $data = $request->validate(['key' => ['required', 'string', 'max:64', 'regex:/^\s*EMK(-[A-Za-z0-9]{5}){4}\s*$/']], ['key.regex' => 'Enter a license key like EMK-XXXXX-XXXXX-XXXXX-XXXXX.']);

        return $this->run(fn () => $this->licenses->activate($data['key'], $this->actor($request)), 'License activated on this store.', 'key');
    }

    public function check(Request $request, StoreLicense $license): RedirectResponse
    {
        $before = $license->installedVersion();
        $license = $this->licenses->check($license, $this->actor($request));
        $after = $license->installedVersion();
        $message = $license->last_error
            ?? ($after !== null && $after !== $before ? "Updated to v{$after}." : 'License checked. No newer version is published.');

        Inertia::flash('toast', $license->last_error === null
            ? ['type' => 'success', 'message' => $message]
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
     * A catalog row with this store's state: installed version, update, licensed here.
     *
     * @param  array<string, mixed>  $item
     * @param  array<string, int>  $licensed  extension name => local license id
     * @return array<string, mixed>
     */
    private function catalogItem(array $item, array $licensed): array
    {
        // Modrix Cloud sends "identifier"; the platform's own marketplace server sends "extension".
        $identifier = (string) ($item['identifier'] ?? $item['extension'] ?? '');
        $free = ($item['is_free'] ?? $item['free'] ?? false) === true;
        $price = $item['price'] ?? null;
        $priceLabel = match (true) {
            $free => null,
            is_array($price) && is_string($price['formatted'] ?? null) => $price['formatted'],
            is_scalar($price) => trim((is_string($item['currency'] ?? null) ? $item['currency'].' ' : '').$price),
            default => null,
        };
        $installed = null;

        if (preg_match('/^[a-z0-9-]+\/[a-z0-9-]+$/', $identifier)) {
            $dir = ($item['type'] ?? null) === 'theme' ? ThemeRepository::packageDir($identifier) : ModuleRepository::packageDir($identifier);
            $file = $dir.'/'.(($item['type'] ?? null) === 'theme' ? 'theme.json' : 'module.json');
            $manifest = is_file($file) ? json_decode((string) file_get_contents($file), true) : null;
            $installed = is_array($manifest) && is_string($manifest['version'] ?? null) ? $manifest['version'] : null;
        }

        $latest = is_string($item['latest_version'] ?? null) ? $item['latest_version'] : null;

        return [
            'identifier' => $identifier,
            'type' => ($item['type'] ?? null) === 'theme' ? 'theme' : 'module',
            'name' => (string) ($item['name'] ?? $identifier),
            'summary' => (string) ($item['summary'] ?? ''),
            'price' => $priceLabel,
            'is_free' => $free,
            'featured' => ($item['featured'] ?? false) === true,
            'thumbnail_url' => $this->safeUrl($item['thumbnail_url'] ?? null),
            'demo_url' => $this->safeUrl($item['demo_url'] ?? null),
            'url' => $this->safeUrl($item['url'] ?? null),
            'latest_version' => $latest,
            'installed_version' => $installed,
            'update_available' => $installed !== null && $latest !== null && Comparator::greaterThan($latest, $installed),
            'licensed_here' => isset($licensed[$identifier]),
            // Updating a licensed package uses its license; others update through the account.
            'license_update_url' => isset($licensed[$identifier]) ? route('admin.modrixcommerce.cloud-connector.install', $licensed[$identifier]) : null,
            // Account fields: only when connected.
            'owned' => ($item['owned'] ?? false) === true,
            'in_use_elsewhere' => (($item['license']['in_use_elsewhere'] ?? false) === true) && ! isset($licensed[$identifier]),
        ];
    }

    /** The version of a module or theme on disk, by name. */
    private function diskVersion(string $identifier): ?string
    {
        foreach ([[ModuleRepository::packageDir($identifier), 'module.json'], [ThemeRepository::packageDir($identifier), 'theme.json']] as [$dir, $file]) {
            $manifest = is_file("{$dir}/{$file}") ? json_decode((string) file_get_contents("{$dir}/{$file}"), true) : null;

            if (is_array($manifest) && is_string($manifest['version'] ?? null)) {
                return $manifest['version'];
            }
        }

        return null;
    }

    /** Links from Modrix Cloud are shown only when they are plain http(s). */
    private function safeUrl(mixed $url): ?string
    {
        return is_string($url) && preg_match('#^https?://#i', $url) ? $url : null;
    }

    /**
     * @param  Closure(): mixed  $operation
     */
    private function run(Closure $operation, string $success, string $field = 'cloud'): RedirectResponse
    {
        try {
            $operation();
        } catch (CloudError|PackageException $e) {
            Inertia::flash('toast', ['type' => 'error', 'message' => $e->getMessage()]);

            throw ValidationException::withMessages([$field => $e->getMessage()]);
        }

        if ($success !== '') {
            Inertia::flash('toast', ['type' => 'success', 'message' => $success]);
        }

        return redirect()->route('admin.modrixcommerce.cloud-connector.index');
    }

    private function back(string $type, string $message): RedirectResponse
    {
        Inertia::flash('toast', ['type' => $type, 'message' => $message]);

        return redirect()->route('admin.modrixcommerce.cloud-connector.index');
    }

    private function actor(Request $request): User
    {
        /** @var User */
        return $request->user();
    }
}
