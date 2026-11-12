<?php

namespace App\Core\Extensions;

use App\Core\Extensions\Models\Extension;
use App\Core\Platform;
use Composer\Semver\Semver;
use Throwable;

/**
 * Whether a module's "platform_version" accepts this store's core. Reads the
 * package's module.json from disk, so it also works for packages the
 * validator rejected (those have no stored manifest).
 */
final class CoreCompatibility
{
    /**
     * Null when the module runs on this core (or declares nothing readable);
     * otherwise what it needs, for a "Core update required" message.
     *
     * @return array{required: string, current: string, message: string}|null
     */
    public static function updateNeeded(Extension $extension): ?array
    {
        $constraint = self::constraint($extension);

        if ($constraint === null) {
            return null;
        }

        try {
            if (Semver::satisfies(Platform::VERSION, $constraint)) {
                return null;
            }
        } catch (Throwable) {
            return null; // An unreadable constraint is reported by the manifest validator.
        }

        return [
            'required' => $constraint,
            'current' => Platform::VERSION,
            'message' => self::message($constraint),
        ];
    }

    public static function message(string $constraint): string
    {
        return "This module needs Modrix Commerce core {$constraint}, but this store runs ".Platform::VERSION
            .'. Update the core in Settings → System status, then enable the module.';
    }

    private static function constraint(Extension $extension): ?string
    {
        $stored = $extension->manifest['platform_version'] ?? null;

        if (is_string($stored) && $stored !== '') {
            return $stored;
        }

        $file = ModuleRepository::packageDir($extension).DIRECTORY_SEPARATOR.Manifest::FILE;

        if (! is_file($file) || filesize($file) > 65536) {
            return null;
        }

        $data = json_decode((string) file_get_contents($file), true);
        $constraint = is_array($data) ? ($data['platform_version'] ?? null) : null;

        return is_string($constraint) && $constraint !== '' ? $constraint : null;
    }
}
