<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Role;

class RoleSeeder extends Seeder
{
    public function run(): void
    {
        $roles = [
            [
                'category'    => 'admin',
                'description' => 'System Administrator - Full access to all features and configurations',
            ],
            [
                'category'    => 'hod',
                'description' => 'Head of Department - Oversees department processes, approvals, and reporting',
            ],
            [
                'category'    => 'engineer',
                'description' => 'Engineer - Responsible for technical execution, project delivery, and maintenance',
            ],
            [
                'category'    => 'accountant',
                'description' => 'Accountant - Handles financial transactions, budgeting, and payments',
            ],
            [
                'category'    => 'staff',
                'description' => 'Regular Staff - Access to assigned business processes and tasks',
            ],
        ];

        foreach ($roles as $data) {
            Role::firstOrCreate(
                ['category' => $data['category']],
                $data
            );
        }

        $this->command->info('Roles seeded successfully!');
        $this->command->table(
            ['ID', 'Category', 'Description'],
            Role::all(['role_id', 'category', 'description'])->toArray()
        );
    }
}