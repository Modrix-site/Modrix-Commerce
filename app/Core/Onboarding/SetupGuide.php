<?php

namespace App\Core\Onboarding;

use App\Core\Extensions\Models\Extension;
use App\Core\Extensions\ModuleState;
use App\Core\Marketplace\ModrixCloudPrompt;
use App\Core\Settings\GeneralSettings;
use App\Core\Settings\SecretSettings;
use App\Core\Settings\SettingsRepository;
use App\Core\Settings\StoreSettings;
use App\Core\Themes\ThemeRepository;
use App\Domains\Catalog\Models\Product;
use App\Domains\Payments\PaymentMethods;
use App\Domains\Shipping\Models\ShippingMethod;
use App\Domains\Tax\Models\TaxRateRow;
use App\Models\User;
use Illuminate\Support\Facades\Route;

/**
 * The first-use setup guide on the dashboard: a checklist that walks a new
 * store owner from store details to themes, modules, products, payments and
 * shipping. Each step is ticked off from the store's real state; optional
 * steps can be skipped. The guide can be hidden and shown again.
 */
final class SetupGuide
{
    private const KEY = 'onboarding';

    public function __construct(
        private readonly SettingsRepository $settings,
        private readonly SecretSettings $secrets,
    ) {}

    /**
     * The steps this user can act on, in order.
     *
     * @return list<array{key: string, title: string, description: string, action: string, url: string, done: bool, skipped: bool, optional: bool}>
     */
    public function steps(User $user): array
    {
        $skipped = $this->skipped();
        $steps = [];

        $cloud = app(ModrixCloudPrompt::class);

        foreach ($this->definitions() as $key => $step) {
            if (! $user->can($step['permission']) || ! Route::has($step['route'])) {
                continue;
            }

            $url = route($step['route']);

            if ($key === 'cloud') {
                if (! $cloud->available()) {
                    continue;
                }
                $url = $cloud->forUser($user)['url'] ?? $url;
            }

            $steps[] = [
                'key' => $key,
                'title' => $step['title'],
                'description' => $step['description'],
                'action' => $step['action'],
                'url' => $url,
                'done' => ($step['done'])(),
                'skipped' => $step['optional'] && in_array($key, $skipped, true),
                'optional' => $step['optional'],
            ];
        }

        return $steps;
    }

    /**
     * What the dashboard shows, or null for staff who do not set up the store.
     *
     * @return array{steps: list<array<string, mixed>>, completed: int, total: int, hidden: bool}|null
     */
    public function forUser(User $user): ?array
    {
        if (! $user->can('settings.manage')) {
            return null;
        }

        $steps = $this->steps($user);
        $completed = count(array_filter($steps, fn (array $step) => $step['done'] || $step['skipped']));

        return ['steps' => $steps, 'completed' => $completed, 'total' => count($steps), 'hidden' => $this->hidden()];
    }

    /** @return list<string> */
    public function keys(): array
    {
        return array_keys($this->definitions());
    }

    public function isOptional(string $key): bool
    {
        return (bool) ($this->definitions()[$key]['optional'] ?? false);
    }

    public function skip(string $key, bool $skip = true): void
    {
        $skipped = array_values(array_diff($this->skipped(), [$key]));

        if ($skip) {
            $skipped[] = $key;
        }

        $this->save(['skipped' => $skipped]);
    }

    public function hide(bool $hide = true): void
    {
        $this->save(['hidden' => $hide]);
    }

    public function hidden(): bool
    {
        return (bool) ($this->state()['hidden'] ?? false);
    }

    /** @return list<string> */
    private function skipped(): array
    {
        return array_values(array_filter((array) ($this->state()['skipped'] ?? []), 'is_string'));
    }

    /** @return array<string, mixed> */
    private function state(): array
    {
        return (array) $this->settings->get(self::KEY, []);
    }

    /** @param array<string, mixed> $changes */
    private function save(array $changes): void
    {
        $this->settings->set(self::KEY, [...$this->state(), ...$changes]);
    }

    /**
     * @return array<string, array{title: string, description: string, action: string, route: string, permission: string, optional: bool, done: \Closure(): bool}>
     */
    private function definitions(): array
    {
        return [
            'store' => [
                'title' => 'Add your store details',
                'description' => 'Your contact email and where you are based, used on invoices, emails and for shipping.',
                'action' => 'Edit store details',
                'route' => 'admin.settings.store.edit',
                'permission' => 'settings.manage',
                'optional' => false,
                'done' => fn () => filled(app(StoreSettings::class)->toArray()['store_email'] ?? null) && filled($this->settings->get('store.country')),
            ],
            'theme' => [
                'title' => 'Choose and customize your theme',
                'description' => 'Pick a look for your storefront, then add your logo, colors and homepage sections.',
                'action' => 'Open themes',
                'route' => 'admin.themes.index',
                'permission' => 'themes.manage',
                'optional' => false,
                'done' => fn () => $this->themeCustomized(),
            ],
            'cloud' => [
                'title' => 'Connect to Modrix Cloud',
                'description' => 'See every theme and module, free and paid, and install them in one click.',
                'action' => 'Connect to Modrix Cloud',
                'route' => 'admin.extensions.index',
                'permission' => 'extensions.install',
                'optional' => true,
                'done' => fn () => $this->secrets->get('cloud.connection_token') !== null,
            ],
            'modules' => [
                'title' => 'Add the features you need',
                'description' => 'Install modules such as payment gateways, reviews or marketing tools.',
                'action' => 'Browse extensions',
                'route' => 'admin.extensions.index',
                'permission' => 'extensions.install',
                'optional' => true,
                'done' => fn () => Extension::query()->where('type', 'module')->where('state', ModuleState::Enabled)
                    ->where('name', '!=', ModrixCloudPrompt::CONNECTOR)->exists(),
            ],
            'product' => [
                'title' => 'Add your first product',
                'description' => 'Add a name, price, photos and stock, or import products from a CSV file.',
                'action' => 'Add a product',
                'route' => 'admin.products.create',
                'permission' => 'products.create',
                'optional' => false,
                'done' => fn () => Product::query()->exists(),
            ],
            'payments' => [
                'title' => 'Set up payments',
                'description' => 'Choose how customers pay: card payments, bank transfer or cash on delivery.',
                'action' => 'Set up payments',
                'route' => 'admin.payments.settings',
                'permission' => 'payments.manage',
                'optional' => false,
                'done' => fn () => app(PaymentMethods::class)->enabled() !== [],
            ],
            'shipping' => [
                'title' => 'Set up shipping',
                'description' => 'Add delivery methods and rates. Skip this if you only sell digital products.',
                'action' => 'Set up shipping',
                'route' => 'admin.shipping-methods.index',
                'permission' => 'shipping.manage',
                'optional' => true,
                'done' => fn () => ShippingMethod::query()->where('is_active', true)->exists(),
            ],
            'taxes' => [
                'title' => 'Set up taxes',
                'description' => 'Add the tax rates you charge. Skip this if you do not charge tax.',
                'action' => 'Set up taxes',
                'route' => 'admin.tax-rates.index',
                'permission' => 'settings.manage',
                'optional' => true,
                'done' => fn () => TaxRateRow::query()->exists(),
            ],
            'email' => [
                'title' => 'Set up store email',
                'description' => 'Send order confirmations and receipts from your own address.',
                'action' => 'Set up email',
                'route' => 'admin.settings.general.edit',
                'permission' => 'settings.manage',
                'optional' => true,
                'done' => fn () => $this->mailConfigured(),
            ],
        ];
    }

    private function themeCustomized(): bool
    {
        $name = app(ThemeRepository::class)->active()->name ?? (string) config('ecomodular.themes.default');
        $prefix = 'theme.'.str_replace('/', '.', $name);

        return filled($this->settings->get($prefix.'.design')) || filled($this->settings->get($prefix.'.sections'));
    }

    private function mailConfigured(): bool
    {
        $mail = app(GeneralSettings::class)->mail();

        if ($mail['driver'] === 'smtp') {
            return $mail['host'] !== '' && $mail['from_address'] !== '';
        }

        // Mail set in the environment counts, unless it only logs or discards messages.
        return ! in_array(config('mail.default'), ['log', 'array', null], true);
    }
}
