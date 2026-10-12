<?php

namespace App\Domains\Catalog\Csv;

final class ImportResult
{
    /**
     * @param  list<string>  $errors
     */
    private function __construct(
        public readonly bool $ok,
        public readonly int $created,
        public readonly int $updated,
        public readonly array $errors,
    ) {}

    /**
     * @param  list<string>  $errors
     */
    public static function failed(array $errors): self
    {
        return new self(false, 0, 0, $errors);
    }

    public static function succeeded(int $created, int $updated): self
    {
        return new self(true, $created, $updated, []);
    }
}
