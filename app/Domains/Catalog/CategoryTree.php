<?php

namespace App\Domains\Catalog;

use App\Domains\Catalog\Models\Category;
use Illuminate\Support\Collection;

/**
 * Builds a depth-annotated, ordered flat list of the category tree.
 */
final class CategoryTree
{
    /**
     * @param  bool  $activeOnly  limit to active categories (storefront)
     * @return list<array{id: int, parent_id: int|null, name: string, slug: string, depth: int, is_active: bool, position: int}>
     */
    public static function flatten(bool $activeOnly = false): array
    {
        $all = Category::query()
            ->when($activeOnly, fn ($q) => $q->where('is_active', true))
            ->orderBy('position')
            ->orderBy('name')
            ->get(['id', 'parent_id', 'name', 'slug', 'is_active', 'position']);

        /** @var Collection<int, Collection<int, Category>> $byParent */
        $byParent = $all->groupBy(fn (Category $c) => $c->parent_id ?? 0);
        $result = [];

        $walk = function (int $parentId, int $depth) use (&$walk, &$result, $byParent): void {
            foreach ($byParent->get($parentId, collect()) as $category) {
                $result[] = [
                    'id' => $category->id,
                    'parent_id' => $category->parent_id,
                    'name' => $category->name,
                    'slug' => $category->slug,
                    'depth' => $depth,
                    'is_active' => $category->is_active,
                    'position' => $category->position,
                ];

                if ($depth < Category::MAX_DEPTH) {
                    $walk($category->id, $depth + 1);
                }
            }
        };

        $walk(0, 0);

        return $result;
    }
}
