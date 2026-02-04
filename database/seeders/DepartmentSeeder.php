<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Department;

class DepartmentSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $departments = [
            [
                'name'     => 'Procurement',
                'location' => 'Head Office - Dar es Salaam',
            ],
            [
                'name'     => 'Engineering',
                'location' => 'Head Office - Dar es Salaam',
            ],
            [
                'name'     => 'Finance & Accounts',
                'location' => 'Head Office - Dar es Salaam',
            ],
            [
                'name'     => 'Human Resources',
                'location' => 'Head Office - Dar es Salaam',
            ],
            [
                'name'     => 'ICT',
                'location' => 'Head Office - Dar es Salaam',
            ],
            // Add more departments as needed, with realistic locations
            [
                'name'     => 'Operations',
                'location' => 'Zonal Office - Arusha',
            ],
            [
                'name'     => 'Logistics',
                'location' => 'Regional Office - Mwanza',
            ],
        ];

        foreach ($departments as $data) {
            Department::firstOrCreate(
                ['name' => $data['name']],
                $data
            );
        }

        $this->command->info('Departments seeded successfully!');
        $this->command->table(
            ['ID', 'Name', 'Location'],
            Department::all(['department_id', 'name', 'location'])->toArray()
        );
    }
}