<?php

namespace EcoModular\ImageStudio\Services;

final readonly class ProcessResult
{
    public function __construct(
        public string $path,
        public string $driver,
        public int $bytesBefore,
        public int $bytesAfter,
        public int $width,
        public int $height,
    ) {}
}
