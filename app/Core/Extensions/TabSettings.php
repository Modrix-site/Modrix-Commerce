<?php

namespace App\Core\Extensions;

use App\Models\User;
use Illuminate\Validation\ValidationException;

/**
 * Module settings that a module puts into a customizer tab (`"tab"` on a
 * setting in module.json). They are the module's own settings: the tab and
 * the module's settings page edit the same stored value.
 */
final class TabSettings
{
    public function __construct(
        private readonly ModuleRepository $modules,
        private readonly ModuleSettingsSchema $settings,
        private readonly ModuleManager $manager,
    ) {}

    /**
     * Enabled modules with settings for this tab.
     *
     * @return list<array{module: string, title: string, fields: list<array<string, mixed>>, values: array<string, mixed>}>
     */
    public function forTab(string $tab): array
    {
        $groups = [];

        foreach ($this->modules->all() as $extension) {
            if ($extension->state !== ModuleState::Enabled || ($manifest = $extension->manifest()) === null) {
                continue;
            }

            $fields = array_values(array_filter($manifest->settings, fn (array $field) => ($field['tab'] ?? null) === $tab));

            if ($fields === []) {
                continue;
            }

            $values = $this->settings->values($manifest);
            $groups[] = [
                'module' => $manifest->name,
                'title' => $manifest->displayName,
                'fields' => $fields,
                'values' => array_intersect_key($values, array_flip(array_column($fields, 'key'))),
            ];
        }

        return $groups;
    }

    /**
     * Saves this module's tab settings; its other settings stay as they are.
     *
     * @param  array<string, mixed>  $input
     *
     * @throws ValidationException
     */
    public function save(string $module, array $input, ?User $actor = null): void
    {
        $extension = $this->modules->find($module);
        $manifest = $extension?->state === ModuleState::Enabled ? $extension->manifest() : null;

        if ($manifest === null) {
            throw ValidationException::withMessages(['settings' => 'This extension is not enabled.']);
        }

        $tabKeys = array_column(array_filter($manifest->settings, fn (array $field) => isset($field['tab'])), 'key');
        $this->manager->configure($module, [...$this->settings->values($manifest), ...array_intersect_key($input, array_flip($tabKeys))], $actor);
    }
}
