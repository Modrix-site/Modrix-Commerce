<?php

namespace App\Core\Themes;

use Closure;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

final class NavigationSchema
{
    public const MAX_DEPTH = 4;

    public const MAX_ITEMS = 150;

    /**
     * @param  array<string, mixed>  $input
     * @return array<string, mixed>
     */
    public static function validate(array $input): array
    {
        $rules = ['footer' => ['required', 'array', 'list', 'size:3']];
        foreach (['header', 'sidebar', 'mobile', 'footer.*'] as $path) {
            $rules[$path] = [$path === 'header' ? 'required' : 'sometimes', 'array:title,links,layout,enabled,inherit_header'];
            if (in_array($path, ['sidebar', 'mobile'], true) && ! array_key_exists($path, $input)) {
                continue;
            }
            $rules["{$path}.title"] = ['required', 'string', 'max:100'];
            $rules["{$path}.layout"] = ['sometimes', Rule::in(['horizontal', 'vertical'])];
            $rules["{$path}.enabled"] = ['sometimes', 'boolean'];
            $rules["{$path}.inherit_header"] = ['sometimes', 'boolean'];
            $rules["{$path}.links"] = ['present', 'array', 'list', 'max:'.($path === 'footer.*' ? 20 : 30)];
            $node = "{$path}.links.*";
            for ($depth = 1; $depth <= self::MAX_DEPTH; $depth++) {
                $rules[$node] = ['required', 'array:label,url,type,page_id,category_id,children'];
                $rules["{$node}.label"] = ['required', 'string', 'max:100'];
                $rules["{$node}.type"] = ['sometimes', 'required', Rule::in(['link', 'page', 'category'])];
                $rules["{$node}.url"] = ["required_if:{$node}.type,link", "required_without:{$node}.type", 'nullable', 'string', 'max:500', function (string $attribute, mixed $value, Closure $fail): void {
                    if (! is_string($value) || ! ThemeSchema::isSafeLink($value) || $value === '') {
                        $fail('Use a relative path starting with / or a secure https:// address.');
                    }
                }];
                $rules["{$node}.page_id"] = ["required_if:{$node}.type,page", 'nullable', 'integer', Rule::exists('pages', 'id')->whereNull('deleted_at')];
                $rules["{$node}.category_id"] = ["required_if:{$node}.type,category", 'nullable', 'integer', 'exists:categories,id'];
                $rules["{$node}.children"] = ['sometimes', 'array', 'list', 'max:'.($depth === self::MAX_DEPTH ? 0 : 30)];
                $node .= '.children.*';
            }
        }
        $data = Validator::make($input, $rules)->validate();
        $count = function (array $links) use (&$count): int {
            return array_sum(array_map(fn (array $item) => 1 + $count($item['children'] ?? []), $links));
        };
        $total = 0;
        foreach ([$data['header'], $data['sidebar'] ?? [], $data['mobile'] ?? [], ...$data['footer']] as $menu) {
            $total += $count($menu['links'] ?? []);
        }
        if ($total > self::MAX_ITEMS) {
            throw ValidationException::withMessages(['navigation' => 'Navigation may contain at most '.self::MAX_ITEMS.' links across all menus.']);
        }

        return $data;
    }
}
