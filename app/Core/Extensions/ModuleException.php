<?php

namespace App\Core\Extensions;

use RuntimeException;

/**
 * A lifecycle operation was refused or failed. The message is safe to show
 * to administrators.
 */
final class ModuleException extends RuntimeException {}
