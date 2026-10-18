<?php

namespace App\Domains\Catalog\Actions;

use App\Core\Audit\AuditLogger;
use App\Domains\Catalog\Models\Category;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

final class DeleteCategory
{
    public function __construct(private readonly AuditLogger $audit) {}

    /**
     * Products are only unlinked from the category, never deleted.
     */
    public function handle(Category $category): void
    {
        if ($category->children()->exists()) {
            throw ValidationException::withMessages(['category' => 'Move or delete the subcategories first.']);
        }

        DB::transaction(function () use ($category) {
            $productCount = $category->products()->count();
            $category->products()->detach();
            $this->audit->record('category.deleted', $category, ['name' => $category->name, 'products_unlinked' => $productCount]);
            $category->delete();
            if ($category->image_path !== null) {
                DB::afterCommit(fn () => Storage::disk($category->image_disk ?? 'public')->delete($category->image_path));
            }
        });
    }
}
