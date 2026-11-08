<?php

namespace EcoModular\ImageStudio\Exceptions;

use RuntimeException;

/** The image cannot be processed meaningfully; the message is shown to the merchant. */
class SkipImage extends RuntimeException {}
