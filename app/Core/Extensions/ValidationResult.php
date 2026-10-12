<?php

namespace App\Core\Extensions;

final class ValidationResult
{
    /**
     * @param  list<string>  $errors
     */
    private function __construct(
        public readonly ?Manifest $manifest,
        public readonly array $errors,
    ) {}

    public static function valid(Manifest $manifest): self
    {
        return new self($manifest, []);
    }

    /**
     * @param  list<string>  $errors
     */
    public static function invalid(array $errors): self
    {
        return new self(null, $errors);
    }

    public function isValid(): bool
    {
        return $this->manifest !== null;
    }
}
