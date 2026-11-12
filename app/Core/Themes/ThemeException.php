<?php

namespace App\Core\Themes;

use RuntimeException;

/**
 * A theme operation was refused. The message is safe to show to staff.
 */
final class ThemeException extends RuntimeException {}
