<?php

namespace EcoModular\ImageStudio\Removers;

use EcoModular\ImageStudio\Contracts\BackgroundRemover;
use EcoModular\ImageStudio\Support\ImageStudioSettings;
use InvalidArgumentException;

class RemoverFactory
{
    public function __construct(private readonly ImageStudioSettings $settings) {}

    public function make(string $driver): BackgroundRemover
    {
        return match ($driver) {
            GdEdgeRemover::KEY => new GdEdgeRemover($this->settings->tolerance(), $this->settings->featherPx()),
            RemoveBgRemover::KEY => new RemoveBgRemover($this->settings->removeBgKey()),
            default => throw new InvalidArgumentException("Unsupported background removal driver [{$driver}]."),
        };
    }
}
