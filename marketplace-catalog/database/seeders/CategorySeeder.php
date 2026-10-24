<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Marketplace\Models\Category;

/** The starting categories (same as the original catalog app). */
class CategorySeeder extends Seeder
{
    public function run(): void
    {
        $categories = [
            ['electronics', 'Electronics', 'Themes for technology, devices and electronics stores.', 'theme'],
            ['fashion', 'Fashion & lifestyle', 'Style-led themes for clothing and lifestyle brands.', 'theme'],
            ['home-living', 'Home & living', 'Themes for furniture, interiors and everyday essentials.', 'theme'],
            ['payments', 'Payments', 'Payment gateways and checkout integrations.', 'module'],
            ['marketing', 'Marketing & sales', 'Extensions for discovery, engagement and conversions.', 'module'],
            ['operations', 'Store operations', 'Tools for shipping, content and store management.', 'module'],
        ];

        foreach ($categories as $position => [$slug, $name, $description, $type]) {
            Category::query()->firstOrCreate(['slug' => $slug], compact('name', 'description', 'type', 'position'));
        }
    }
}
