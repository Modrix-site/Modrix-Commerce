<?php

namespace EcoModular\ImageStudio\Support;

use App\Core\Extensions\ModuleSettingsSchema;
use App\Core\Settings\SecretSettings;
use EcoModular\ImageStudio\ImageStudioServiceProvider;

/**
 * Typed, range-clamped access to the module's settings.
 */
final class ImageStudioSettings
{
    public const DRIVERS = ['gd-edge', 'removebg'];

    private const SECRET = 'image_studio.removebg_api_key';

    public function __construct(
        private readonly ModuleSettingsSchema $schema,
        private readonly SecretSettings $secrets,
    ) {}

    public function enabled(): bool
    {
        return $this->bool('enabled', true);
    }

    public function processOnUpload(): bool
    {
        return $this->bool('process_on_upload', true);
    }

    public function removeBackground(): bool
    {
        return $this->bool('remove_background', true);
    }

    public function driver(): string
    {
        $driver = $this->schema->of(ImageStudioServiceProvider::MODULE, 'driver', 'gd-edge');

        return is_string($driver) && in_array($driver, self::DRIVERS, true) ? $driver : 'gd-edge';
    }

    public function tolerance(): int
    {
        return $this->int('tolerance', 18, 1, 100);
    }

    public function featherPx(): int
    {
        return $this->int('feather_px', 1, 0, 4);
    }

    public function webpQuality(): int
    {
        return $this->int('webp_quality', 82, 40, 100);
    }

    public function removeBgKey(): ?string
    {
        return $this->secrets->get(self::SECRET);
    }

    public function setRemoveBgKey(?string $key): void
    {
        $this->secrets->put(self::SECRET, $key);
    }

    public function hasRemoveBgKey(): bool
    {
        return $this->secrets->has(self::SECRET);
    }

    private function bool(string $key, bool $default): bool
    {
        return (bool) $this->schema->of(ImageStudioServiceProvider::MODULE, $key, $default);
    }

    private function int(string $key, int $default, int $min, int $max): int
    {
        $value = $this->schema->of(ImageStudioServiceProvider::MODULE, $key, $default);

        return max($min, min($max, is_numeric($value) ? (int) $value : $default));
    }
}
