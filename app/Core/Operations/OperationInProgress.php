<?php

namespace App\Core\Operations;

use RuntimeException;

/** A backup or restore is already queued or running. */
final class OperationInProgress extends RuntimeException {}
