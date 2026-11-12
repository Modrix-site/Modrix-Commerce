<?php

namespace App\Core\Installation;

/**
 * Auto: the host provides a configured, migrated database (Git deploys such
 * as Uplary), so the installer only creates the owner. Manual: shared hosting
 * (cPanel, Plesk, DirectAdmin...), where the installer sets up the database.
 */
enum InstallMode: string
{
    case Auto = 'auto';
    case Manual = 'manual';
}
