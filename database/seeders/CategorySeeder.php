<?php

namespace Database\Seeders;

use App\Models\Category;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class CategorySeeder extends Seeder
{
    public function run(): void
    {
        $categories = [
            'Motivation' => 'تحفيز',
            'Wisdom' => 'حكمة',
            'Love' => 'حب',
            'Success' => 'نجاح',
            'Life' => 'حياة',
            'Friendship' => 'صداقة',
            'Faith' => 'إيمان',
            'Books' => 'كتب',
            'Humor' => 'فكاهة',
        ];

        foreach ($categories as $name => $nameAr) {
            Category::updateOrCreate(['slug' => Str::slug($name)], ['name' => $name, 'name_ar' => $nameAr]);
        }
    }
}
