<?php

namespace App\Domains\Catalog;

use App\Domains\Catalog\Models\Category;

/**
 * The storefront's category tree for menus (the mobile menu, mega menus):
 * active categories in their order, with subcategories, three levels deep.
 * A category below an inactive one is left out with it.
 */
final class CategoryMenu
{
    public const DEPTH = 3;

    /** Most categories in the tree, to keep page data small. */
    public const MAX = 300;

    /**
     * @return list<array{id: int, name: string, slug: string, url: string, children: list<array<string, mixed>>}>
     */
    public function tree(): array
    {
        $categories = Category::query()->active()->withCurrentTranslation()
            ->orderBy('position')->orderBy('name')
            ->limit(self::MAX)
            ->get(['id', 'parent_id', 'name', 'slug']);

        return $this->children($categories->groupBy(fn (Category $c) => (int) $c->parent_id)->all(), 0, 1);
    }

    /**
     * @param  array<int, iterable<Category>>  $byParent
     * @return list<array{id: int, name: string, slug: string, url: string, children: list<array<string, mixed>>}>
     */
    private function children(array $byParent, int $parentId, int $depth): array
    {
        $nodes = [];

        foreach ($byParent[$parentId] ?? [] as $category) {
            $nodes[] = [
                'id' => $category->id,
                'name' => (string) $category->localized('name'),
                'slug' => $category->slug,
                'url' => '/c/'.$category->slug,
                'children' => $depth < self::DEPTH ? $this->children($byParent, $category->id, $depth + 1) : [],
            ];
        }

        return $nodes;
    }
}
