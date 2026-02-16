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
                'dashboard'   => 'Dashboard.vue'
            ],
            [
                'category'    => 'hod',
                'description' => 'Head of Department - Oversees department processes, approvals, and reporting',
                'dashboard'   => 'HodDashboard.vue'
            ],
            [
                'category'    => 'tender',
                'description' => 'Tender Manager - Manages tender creation, evaluation, and awarding processes',
                'dashboard'   => 'TendersDashboard.vue'
            ],
            [
                'category'    => 'hr',
                'description' => 'HR Manager - Handles human resources, employee management, and payroll',
                'dashboard'   => 'HrDashboard.vue'
            ],
            [
                'category'    => 'engineer',
                'description' => 'Engineer - Responsible for technical execution, project delivery, and maintenance',
                'dashboard'   => 'UserDashboard.vue'
            ],
            [
                'category'    => 'accountant',
                'description' => 'Accountant - Handles financial transactions, budgeting, and payments',
                'dashboard'   => 'AccountantDashboard.vue'
            ],
            [
                'category'    => 'staff',
                'description' => 'Regular Staff - Access to assigned business processes and tasks',
                'dashboard'   => 'TendersDashboard.vue'
            ],
            [
                'category'    => 'user',
                'description' => 'Regular User - Access to assigned business processes and tasks',
                'dashboard'   => 'UserDashboard.vue'
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