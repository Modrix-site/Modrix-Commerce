<?php

namespace App\Core\Extensions;

/**
 * Lifecycle states of a module. Only `Enabled` modules are loaded.
 *
 *   discovered → installed → enabled ⇄ disabled
 *   (files updated) → update_pending → (update) → previous enabled/disabled
 *   any failure → failed; manifest problems → invalid
 */
enum ModuleState: string
{
    /** Found on disk with a valid manifest; nothing installed. */
    case Discovered = 'discovered';

    /** Found on disk, but the manifest does not validate. */
    case Invalid = 'invalid';

    /** Migrations ran; not loaded until enabled. */
    case Installed = 'installed';

    case Enabled = 'enabled';

    /** Was enabled; not loaded; data kept. */
    case Disabled = 'disabled';

    /** Newer files than the installed version; not loaded until updated. */
    case UpdatePending = 'update_pending';

    /** An install, update or boot failed; not loaded. */
    case Failed = 'failed';

    public function label(): string
    {
        return match ($this) {
            self::Discovered => 'Not installed',
            self::Invalid => 'Invalid package',
            self::Installed => 'Installed',
            self::Enabled => 'Enabled',
            self::Disabled => 'Disabled',
            self::UpdatePending => 'Update pending',
            self::Failed => 'Failed',
        };
    }
}
