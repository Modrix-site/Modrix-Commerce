<?php

namespace App\Core\Extensions;

use App\Core\Settings\SettingsRepository;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

/**
 * Where the merchant shows each extension block: its place, order and
 * whether it shows. Stored for the whole store (every theme). Entries of
 * blocks that are not registered (module disabled) are kept, so a block
 * returns where it was when its module comes back.
 */
final class BlockPlacements
{
    public const KEY = 'extensions.blocks';

    public const MAX_ORDER = 999;

    public function __construct(private readonly SettingsRepository $settings) {}

    /**
     * Stored entries as saved (read them through for()).
     *
     * @return array<string, mixed>
     */
    public function all(): array
    {
        $stored = $this->settings->get(self::KEY);

        return is_array($stored) ? $stored : [];
    }

    /**
     * @return array{place: string, order: int, enabled: bool}|null
     */
    public function for(string $id): ?array
    {
        $entry = $this->all()[$id] ?? null;

        return is_array($entry) && is_string($entry['place'] ?? null) ? [
            'place' => $entry['place'],
            'order' => (int) ($entry['order'] ?? 0),
            'enabled' => (bool) ($entry['enabled'] ?? true),
        ] : null;
    }

    /**
     * Validates and stores placements for registered blocks.
     *
     * @param  array<string, mixed>  $input  block id => {place, order, enabled}
     *
     * @throws ValidationException
     */
    public function save(array $input, StorefrontSlots $slots): void
    {
        $blocks = collect($slots->blocks())->keyBy('id');
        $errors = [];
        $clean = [];

        foreach ($input as $id => $entry) {
            $block = $blocks->get((string) $id);
            $field = 'blocks.'.str_replace('.', '_', (string) $id);

            if ($block === null) {
                $errors[$field] = 'This block is not available.';

                continue;
            }

            $validator = Validator::make(is_array($entry) ? $entry : [], [
                'place' => ['required', 'string', 'in:'.implode(',', $block['places'])],
                'order' => ['required', 'integer', 'min:0', 'max:'.self::MAX_ORDER],
                'enabled' => ['required', 'boolean'],
            ]);

            if ($validator->fails()) {
                $errors[$field] = "{$block['label']}: ".$validator->errors()->first();

                continue;
            }

            $data = $validator->validated();
            $clean[(string) $id] = ['place' => $data['place'], 'order' => (int) $data['order'], 'enabled' => (bool) $data['enabled']];
        }

        if ($errors !== []) {
            throw ValidationException::withMessages($errors);
        }

        // Keep entries of blocks whose modules are currently off.
        $this->settings->set(self::KEY, array_replace($this->all(), $clean));
    }
}
