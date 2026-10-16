<?php

namespace App\Domains\Catalog;

use Illuminate\Support\Str;

/**
 * Outcome of a bulk product action: how many products changed and which
 * were skipped (or only partly changed), with the reason.
 */
final class BulkResult
{
    public int $updated = 0;

    /** @var list<array{id: int, name: string, reason: string}> */
    public array $skipped = [];

    public function skip(int $id, string $name, string $reason): void
    {
        $this->skipped[] = ['id' => $id, 'name' => $name, 'reason' => $reason];
    }

    /** "Updated 3 products. Skipped 1: Mug (no active variant with a price)." */
    public function message(): string
    {
        $message = 'Updated '.$this->updated.' '.Str::plural('product', $this->updated).'.';

        if ($this->skipped !== []) {
            $list = array_map(fn (array $s) => "{$s['name']} ({$s['reason']})", array_slice($this->skipped, 0, 10));
            $message .= ' Skipped '.count($this->skipped).': '.implode(', ', $list).(count($this->skipped) > 10 ? ', …' : '').'.';
        }

        return $message;
    }
}
