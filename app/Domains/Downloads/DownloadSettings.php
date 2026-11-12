<?php

namespace App\Domains\Downloads;

use App\Core\Settings\SettingsRepository;

/**
 * Store-wide rules for new download links: how many downloads each link
 * allows and how long it lasts. Existing links keep the rules they got.
 */
final class DownloadSettings
{
    public const DEFAULT_LIMIT = 5;

    public const DEFAULT_EXPIRY_DAYS = 30;

    public function __construct(private readonly SettingsRepository $settings) {}

    /** Downloads per file; 0 = unlimited. */
    public function limit(): int
    {
        return (int) $this->settings->get('downloads.limit', self::DEFAULT_LIMIT);
    }

    /** Days a link lasts after payment; 0 = never expires. */
    public function expiryDays(): int
    {
        return (int) $this->settings->get('downloads.expiry_days', self::DEFAULT_EXPIRY_DAYS);
    }

    public function save(int $limit, int $expiryDays): void
    {
        $this->settings->setMany(['downloads.limit' => $limit, 'downloads.expiry_days' => $expiryDays]);
    }
}
