<?php

namespace Database\Factories;

use App\Domains\Catalog\Models\Category;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Category>
 */
class CategoryFactory extends Factory
{
    protected $model = Category::class;

    public function definition(): array
    {
        $name = Str::title(implode(' ', (array) fake()->unique()->words(2)));

        return [
            'name' => $name,
            'slug' => Str::slug($name).'-'.Str::lower(Str::random(4)),
            'is_active' => true,
            'position' => 0,
        ];
    }
}
