<?php

namespace Marketplace\Services;

use RuntimeException;

/** A webhook request that is not a genuine, fresh provider event. */
class InvalidWebhook extends RuntimeException {}
