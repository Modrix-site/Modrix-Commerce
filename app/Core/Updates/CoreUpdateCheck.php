<?php

namespace App\Core\Updates;

use App\Core\Settings\SettingsRepository;

/**
 * Asks Modrix Cloud for newer core releases and remembers the newest one
 * that verifies. Failures are stored, never thrown to the caller.
 */
final class CoreUpdateCheck
{
    public function __construct(
        private readonly CoreUpdateClient $client,
        private readonly CoreReleaseVerifier $verifier,
        private readonly SettingsRepository $settings,
    ) {}

    /** @return array<string, mixed>|null */
    public function run(): ?array
    {
        try {
            $items = $this->client->releases(\App\Core\Platform::VERSION, PHP_VERSION);
        } catch (CoreUpdateError $e) {
            $this->settings->set('core_update.last_error', $e->getMessage());
            $this->settings->set('core_update.checked_at', now()->toIso8601String());

            return null;
        }

        $newest = null;

        foreach ($items as $item) {
            $verified = $this->verifier->verify($item);

            if ($verified !== null && ($newest === null || $this->verifier->newer($verified['version'], $newest['version']))) {
                $newest = $verified + ['item' => $item];
            }
        }

        $this->settings->set('core_update.available', $newest);
        $this->settings->set('core_update.checked_at', now()->toIso8601String());
        $this->settings->set('core_update.last_error', null);

        return $newest;
    }

    /**
     * The raw signed item of the available release, as Modrix Cloud served it.
     * Runs keep it because the installer verifies the signature again.
     *
     * @return array<string, mixed>|null
     */
    public function item(): ?array
    {
        $item = $this->available()['item'] ?? null;

        return is_array($item) ? $item : null;
    }

    /** @return array<string, mixed>|null the verified fields plus the raw `item` */
    public function available(): ?array
    {
        $available = $this->settings->get('core_update.available');

        return is_array($available) ? $available : null;
    }
}
