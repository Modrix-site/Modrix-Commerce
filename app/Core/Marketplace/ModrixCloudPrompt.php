<?php

namespace App\Core\Marketplace;

use App\Core\Extensions\ModuleRepository;
use App\Core\Extensions\ModuleState;
use App\Core\Settings\SecretSettings;
use App\Models\User;
use Illuminate\Support\Facades\Route;

/**
 * Invites the store owner to connect to Modrix Cloud, where every theme and
 * module (free and paid) is listed. Shown on the dashboard and the
 * Extensions page until the store is connected.
 *
 * The connection itself belongs to the bundled Modrix Cloud Connector module;
 * this only reads whether it is enabled and connected.
 */
final class ModrixCloudPrompt
{
    public const CONNECTOR = 'modrixcommerce/cloud-connector';

    /** Where the connector keeps its encrypted connection token (see CloudClient::TOKEN_KEY). */
    private const TOKEN_KEY = 'cloud.connection_token';

    public function __construct(
        private readonly ModuleRepository $modules,
        private readonly SecretSettings $secrets,
    ) {}

    /** Whether the bundled connector is on disk at all. */
    public function available(): bool
    {
        return $this->modules->find(self::CONNECTOR) !== null;
    }

    /**
     * What to show this user, or null when there is nothing to ask: the store is
     * connected, the connector is missing, or the user cannot manage extensions.
     *
     * @return array{state: 'disabled'|'disconnected', url: string}|null
     */
    public function forUser(User $user): ?array
    {
        if (! $user->can('extensions.view')) {
            return null;
        }

        $connector = $this->modules->find(self::CONNECTOR);

        if ($connector === null) {
            return null;
        }

        // Disabled: enabling the connector comes first.
        if ($connector->state !== ModuleState::Enabled) {
            return ['state' => 'disabled', 'url' => route('admin.extensions.show', $connector)];
        }

        if ($this->secrets->get(self::TOKEN_KEY) !== null) {
            return null;
        }

        // Enabled but not connected: the connector's page holds the Connect button
        // (it asks for the password before linking an account).
        $page = 'admin.modrixcommerce.cloud-connector.index';

        return Route::has($page) ? ['state' => 'disconnected', 'url' => route($page)] : null;
    }
}
