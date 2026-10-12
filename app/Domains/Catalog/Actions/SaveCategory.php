<?php

namespace App\Domains\Catalog\Actions;

use App\Core\Audit\AuditLogger;
use App\Domains\Catalog\Models\Category;
use App\Domains\Catalog\Slugger;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

final class SaveCategory
{
    public function __construct(private readonly AuditLogger $audit) {}

    /**
     * @param  array{name: string, slug?: string|null, parent_id?: int|null, description?: string|null, position?: int|null, is_active?: bool, meta_title?: string|null, meta_description?: string|null}  $data
     */
    public function handle(?Category $category, array $data): Category
    {
        return DB::transaction(function () use ($category, $data) {
            $isNew = $category === null;
            $category ??= new Category;
            $parentId = $data['parent_id'] ?? null;

            if ($parentId !== null) {
                $this->assertValidParent($category, (int) $parentId);
            }

            $slugSource = filled($data['slug'] ?? null) ? (string) $data['slug'] : $data['name'];
            $slug = Str::slug($slugSource);

            if (Category::query()->where('slug', $slug)->when(! $isNew, fn ($q) => $q->whereKeyNot($category->id))->exists()) {
                if (filled($data['slug'] ?? null)) {
                    throw ValidationException::withMessages(['slug' => 'This slug is already used by another category.']);
                }
                $slug = Slugger::unique(Category::class, $slugSource, $category->id);
            }

            $category->fill([
                'name' => $data['name'],
                'slug' => $slug,
                'parent_id' => $parentId,
                'description' => $data['description'] ?? null,
                'position' => $data['position'] ?? 0,
                'is_active' => $data['is_active'] ?? true,
                'meta_title' => $data['meta_title'] ?? null,
                'meta_description' => $data['meta_description'] ?? null,
            ])->save();

            $this->audit->record($isNew ? 'category.created' : 'category.updated', $category, ['name' => $category->name]);

            return $category;
        });
    }

    private function assertValidParent(Category $category, int $parentId): void
    {
        $parent = Category::query()->find($parentId);

        if ($parent === null) {
            throw ValidationException::withMessages(['parent_id' => 'The selected parent category does not exist.']);
        }

        if ($category->exists && in_array($parent->id, $category->descendantIdsAndSelf(), true)) {
            throw ValidationException::withMessages(['parent_id' => 'A category cannot be placed inside itself or one of its subcategories.']);
        }

        if (count($parent->ancestors()) + 1 >= Category::MAX_DEPTH) {
            throw ValidationException::withMessages(['parent_id' => 'Categories can be nested at most '.Category::MAX_DEPTH.' levels deep.']);
        }
    }
}
