<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use App\Models\User;

class UserSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $users = [
            // Super Admin / System Owner
            [
                'name'          => 'System Administrator',
                'email'         => 'admin@teratech.co.tz',
                'password'      => Hash::make('12341234'),
                'role_id'       => 1,           
                'department_id' => null,
                'status'        => 'is_active',
            ],

            // Head of Department (HOD) example
            [
                'name'          => 'Aisha Mohamed',
                'email'         => 'hod.procurement@teratech.co.tz',
                'password'      => Hash::make('12341234'),
                'role_id'       => 2,          
                'department_id' => 1,          
                'status'        => 'is_active',
            ],

            // Engineer example
            [
                'name'          => 'John Kweka',
                'email'         => 'engineer@teratech.co.tz',
                'password'      => Hash::make('12341234'),
                'role_id'       => 3,           
                'department_id' => 2,
                'status'        => 'is_active',
            ],

            //Tender Manager example
            [
                'name'          => 'Mary Njeri',
                'email'         => 'tender@teratech.co.tz',
                'password'      => Hash::make('12341234'),
                'role_id'       => 4,          
                'department_id' => 3,
                'status'        => 'is_active',
            ],

            // Accountant example
            [
                'name'          => 'Fatuma Hassan',
                'email'         => 'accountant@teratech.co.tz',
                'password'      => Hash::make('12341234'),
                'role_id'       => 5,           
                'department_id' => 4,
                'status'        => 'is_active',
            ],

            // Regular user / Staff example/hr
            [
                'name'          => 'Peter Mushi',
                'email'         => 'peter.mushi@teratech.co.tz',
                'password'      => Hash::make('12341234'),
                'role_id'       => 6,          
                'department_id' => 1,
                'status'        => 'is_active',
            ],

            // Inactive user example (for testing locked accounts)
            [
                'name'          => 'Test Inactive User',
                'email'         => 'inactive@teratech.co.tz',
                'password'      => Hash::make('Inactive@2025!'),
                'role_id'       => 3,
                'department_id' => null,
                'status'        => 'inactive',   // ← Will fail login due to status check
            ],
        ];

        foreach ($users as $userData) {
            // Use firstOrCreate to avoid duplicates if you re-run the seeder
            User::firstOrCreate(
                ['email' => $userData['email']],
                $userData
            );
        }

        $this->command->info('Users seeded successfully! You can now log in with:');
        $this->command->info('Email: admin@teratech.co.tz       Password: Admin@2025!');
        $this->command->info('Email: peter.mushi@teratech.co.tz Password: Peter@2025!');
        $this->command->info('And other test accounts...');
    }
}