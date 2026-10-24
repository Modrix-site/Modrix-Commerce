<?php

namespace Marketplace\Services;

use RuntimeException;

/** A setup step that cannot continue; the message is shown to the person installing. */
class InstallerException extends RuntimeException {}
