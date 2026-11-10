<?php

namespace App\Http\Controllers\Admin;

use App\Core\Extensions\Manifest;
use App\Core\Extensions\ManifestValidator;
use App\Core\Extensions\Models\Extension;
use App\Core\Extensions\Models\ExtensionEvent;
use App\Core\Extensions\ModuleException;
use App\Core\Extensions\ModuleManager;
use App\Core\Extensions\ModuleRepository;
use App\Core\Extensions\ModuleSettingsSchema;
use App\Core\Extensions\ModuleState;
use App\Core\Marketplace\Client\StoreLicense;
use App\Http\Controllers\Controller;
use App\Models\User;
use Closure;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Admin UI for the module lifecycle. Every state change goes through
 * ModuleManager; installing, enabling, updating and uninstalling execute or
 * remove code and data, so they also require a recent password confirmation.
 */
class ExtensionController extends Controller
{
    public function __construct(
        private readonly ModuleRepository $repository,
        private readonly ModuleManager $manager,
    ) {}

    public function index(Request $request): Response
    {
        $this->repository->sync($this->actor($request));

        return Inertia::render('admin/extensions/index', [
            'extensions' => $this->repository->all()->map(fn (Extension $e) => $this->summary($e))->values(),
            'safeMode' => (bool) config('ecomodular.modules.safe_mode'),
            'licensedExtensions' => StoreLicense::query()->where('type', 'module')->where('status', 'active')->pluck('extension_name')->values(),
            // Modrix Cloud needs the bundled connector; the page points to it until it is enabled.
            'cloudConnector' => fn () => ($connector = $this->repository->find('modrixcommerce/cloud-connector')) === null ? null : [
                'id' => $connector->id,
                'enabled' => $connector->state === ModuleState::Enabled,
            ],
        ]);
    }

    public function show(Extension $extension, ModuleSettingsSchema $settings): Response
    {
        abort_unless($extension->type === 'module', 404);
        $manifest = $extension->manifest();
        $dependents = $this->repository->all()
            ->filter(fn (Extension $e) => array_key_exists($extension->name, (array) ($e->manifest['requires'] ?? [])))
            ->map(fn (Extension $e) => ['name' => $e->name, 'state' => $e->state->value])
            ->values();

        return Inertia::render('admin/extensions/show', [
            'extension' => [
                ...$this->summary($extension),
                'license' => $manifest?->license,
                'platform_version' => $manifest?->platformVersion,
                'permissions' => $manifest->permissions ?? [],
                'routes' => array_keys($manifest->routes ?? []),
                'has_migrations' => $manifest?->migrations !== null,
                'dependents' => $dependents,
                'protected' => ModuleManager::isProtected($extension->name),
                'installed_at' => $extension->installed_at?->toIso8601String(),
                'enabled_at' => $extension->enabled_at?->toIso8601String(),
            ],
            'settings' => $manifest !== null && $extension->isInstalled() ? [
                'fields' => $manifest->settings,
                'values' => $settings->values($manifest),
            ] : null,
            'events' => ExtensionEvent::query()->with('actor:id,email')
                ->where('extension', $extension->name)->latest('id')->limit(50)->get()
                ->map(fn (ExtensionEvent $e) => [
                    'id' => $e->id,
                    'action' => $e->action,
                    'from_state' => $e->from_state,
                    'to_state' => $e->to_state,
                    'version' => $e->version,
                    'success' => $e->success,
                    'message' => $e->message,
                    'actor' => $e->actor?->email,
                    'created_at' => $e->created_at->toIso8601String(),
                ]),
        ]);
    }

    public function install(Request $request, Extension $extension): RedirectResponse
    {
        return $this->run($extension, fn () => $this->manager->install($extension->name, $this->actor($request)), 'Module installed. Enable it to start using it.');
    }

    public function enable(Request $request, Extension $extension): RedirectResponse
    {
        $request->validate(['trusted' => ['accepted']], ['trusted.accepted' => 'Confirm that you trust this module\'s code.']);

        return $this->run($extension, fn () => $this->manager->enable($extension->name, true, $this->actor($request)), 'Module enabled.');
    }

    public function disable(Request $request, Extension $extension): RedirectResponse
    {
        return $this->run($extension, fn () => $this->manager->disable($extension->name, $this->actor($request)), 'Module disabled. Its data has been kept.');
    }

    public function update(Request $request, Extension $extension): RedirectResponse
    {
        return $this->run($extension, fn () => $this->manager->update($extension->name, $this->actor($request)), 'Module updated.');
    }

    public function configure(Request $request, Extension $extension): RedirectResponse
    {
        $input = (array) $request->input('settings', []);

        return $this->run($extension, fn () => $this->manager->configure($extension->name, $input, $this->actor($request)), 'Settings saved.');
    }

    public function uninstall(Request $request, Extension $extension): RedirectResponse
    {
        $data = $request->validate(['confirmation' => ['required', 'string', 'max:100']]);

        return $this->run($extension, fn () => $this->manager->uninstall($extension->name, $data['confirmation'], $this->actor($request)), 'Module uninstalled and its data removed.');
    }

    /**
     * Deletes a module that is not running: its data (if installed), its files
     * and its record. The extension record is gone afterwards, so this returns
     * to the list instead of the detail page.
     */
    public function destroy(Request $request, Extension $extension): RedirectResponse
    {
        abort_unless($extension->type === 'module', 404);
        $data = $request->validate(['confirmation' => ['required', 'string', 'max:100']]);
        $name = $extension->display_name;

        try {
            $this->manager->delete($extension->name, $data['confirmation'], $this->actor($request));
        } catch (ModuleException $e) {
            Inertia::flash('toast', ['type' => 'error', 'message' => $e->getMessage()]);

            throw ValidationException::withMessages(['module' => $e->getMessage()]);
        }

        Inertia::flash('toast', ['type' => 'success', 'message' => __(':name was deleted.', ['name' => $name])]);

        return redirect()->route('admin.extensions.index');
    }

    /**
     * @param  Closure(): Extension  $operation
     */
    private function run(Extension $extension, Closure $operation, string $success): RedirectResponse
    {
        abort_unless($extension->type === 'module', 404);

        try {
            $operation();
        } catch (ModuleException $e) {
            Inertia::flash('toast', ['type' => 'error', 'message' => $e->getMessage()]);

            throw ValidationException::withMessages(['module' => $e->getMessage()]);
        }

        Inertia::flash('toast', ['type' => 'success', 'message' => __($success)]);

        return redirect()->route('admin.extensions.show', $extension);
    }

    /**
     * @return array<string, mixed>
     */
    private function summary(Extension $extension): array
    {
        $manifest = $extension->manifest();

        return [
            'id' => $extension->id,
            'name' => $extension->name,
            'display_name' => $extension->display_name,
            'description' => $extension->description,
            'author' => $manifest?->author,
            'icon' => $manifest === null ? 'puzzle' : $manifest->icon,
            'accent' => $manifest?->accent,
            'logo' => $this->logo($extension, $manifest),
            'state' => $extension->state->value,
            'state_label' => $extension->state->label(),
            'version' => $extension->version,
            'available_version' => $extension->available_version,
            'update_available' => $extension->state === ModuleState::UpdatePending
                || ($extension->state === ModuleState::Failed && $extension->isInstalled() && $extension->available_version !== $extension->version),
            'failure' => $extension->failure,
            'errors' => $extension->errors ?? [],
            'requires' => $manifest->requires ?? [],
            'capabilities' => array_map(fn (string $c) => ['key' => $c, 'label' => Manifest::CAPABILITIES[$c] ?? $c], $manifest->capabilities ?? []),
        ];
    }

    private function logo(Extension $extension, ?Manifest $manifest): ?string
    {
        if ($manifest?->logo !== null) {
            $file = ManifestValidator::resolvePath(ModuleRepository::packageDir($extension), $manifest->logo, directory: false);
            if ($file !== null && filesize($file) <= 262144) {
                $info = @getimagesize($file);
                if ($info !== false && in_array($info['mime'], ['image/png', 'image/jpeg', 'image/webp'], true)) {
                    $bytes = file_get_contents($file);
                    if ($bytes !== false) {
                        return 'data:'.$info['mime'].';base64,'.base64_encode($bytes);
                    }
                }
            }
        }

        return match ($extension->name) {
            'modrixcommerce/tabby' => '/images/extensions/tabby.svg',
            'modrixcommerce/tamara' => '/images/extensions/tamara.svg',
            default => null,
        };
    }

    private function actor(Request $request): User
    {
        /** @var User */
        return $request->user();
    }
}
