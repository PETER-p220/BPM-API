<?php

namespace Database\Seeders;

// use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $this->call([
        // Other seeders first if needed
        \Database\Seeders\RoleSeeder::class,
        \Database\Seeders\DepartmentSeeder::class,
        \Database\Seeders\UserSeeder::class,
    ]);
    }
}
