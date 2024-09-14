<?php

namespace Database\Seeders;

use App\Models\Product;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class ProductSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        Product::insert([
            [
                'name' => 'Laptop',
                'price' => 1200.00,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'name' => 'Smartphone',
                'price' => 800.00,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'name' => 'Tablet',
                'price' => 500.00,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'name' => 'Headphones',
                'price' => 150.00,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'name' => 'Smartwatch',
                'price' => 200.00,
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ]);
    }
}
