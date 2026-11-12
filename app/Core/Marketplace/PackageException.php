<?php

namespace App\Core\Marketplace;

use RuntimeException;

/**
 * A package or its metadata failed an integrity or safety check. The
 * message is safe to show to administrators.
 */
final class PackageException extends RuntimeException {}
