<?php

namespace App\Domains\Catalog\Actions;

use App\Core\Audit\AuditLogger;
use App\Core\Media\ImageSanitizer;
use App\Core\Media\MediaLibrary;
use App\Domains\Catalog\Models\Category;
use App\Domains\Catalog\Slugger;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

final class SaveCategory
{
    public function __construct(private readonly AuditLogger $audit, private readonly ImageSanitizer $images) {}

    /**
     * @param  array{name: string, slug?: string|null, parent_id?: int|null, description?: string|null, position?: int|null, is_active?: bool, meta_title?: string|null, meta_description?: string|null, image?: UploadedFile|null, remove_image?: bool}  $data
     */
    public function handle(?Category $category, array $data): Category
    {
        $newPath = null;
        $disk = MediaLibrary::disk();
        try {
            return DB::transaction(function () use ($category, $data, &$newPath, $disk) {
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

                $oldPath = $category->image_path;
                $oldDisk = $category->image_disk ?? 'public';
                if (($data['image'] ?? null) instanceof UploadedFile) {
                    $clean = $this->images->sanitize($data['image']->getRealPath() ?: '', 'image');
                    $newPath = 'categories/'.Str::random(40).'.'.$clean['extension'];
                    Storage::disk($disk)->put($newPath, $clean['bytes'], ['visibility' => 'public']);
                    $category->image_disk = $disk;
                    $category->image_path = $newPath;
                } elseif ($data['remove_image'] ?? false) {
                    $category->image_disk = null;
                    $category->image_path = null;
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

                if ($oldPath !== null && $oldPath !== $category->image_path) {
                    DB::afterCommit(fn () => Storage::disk($oldDisk)->delete($oldPath));
                }

                return $category;
            });
        } catch (\Throwable $exception) {
            if ($newPath !== null) {
                Storage::disk($disk)->delete($newPath);
            }
            throw $exception;
        }
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
