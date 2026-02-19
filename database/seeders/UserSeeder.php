<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use App\Models\User;

class UserSeeder extends Seeder
{
    /**
     * Role ID mapping (must match RoleSeeder insert order):
     *   1 = admin
     *   2 = hod
     *   3 = engineer
     *   4 = tender
     *   5 = accountant
     *   6 = hr
     */
    public function run(): void
    {
        $users = [
            // ── Admin (role_id: 1) ────────────────────────────────────────
            [
                'name'          => 'System Administrator',
                'email'         => 'admin@teratech.co.tz',
                'password'      => Hash::make('12341234'),
                'role_id'       => 1,
                'department_id' => null,
                'status'        => 'is_active',
            ],

            // ── Head of Department (role_id: 2) ──────────────────────────
            [
                'name'          => 'Aisha Mohamed',
                'email'         => 'hod.procurement@teratech.co.tz',
                'password'      => Hash::make('12341234'),
                'role_id'       => 2,
                'department_id' => 1,
                'status'        => 'is_active',
            ],

            // ── Engineer (role_id: 3) → AuthLayout1 / UserDashboard ───────
            [
                'name'          => 'John Kweka',
                'email'         => 'engineer@teratech.co.tz',
                'password'      => Hash::make('12341234'),
                'role_id'       => 3,
                'department_id' => 2,
                'status'        => 'is_active',
            ],

            // ── Tender Manager (role_id: 4) → AuthLayout2 / TendersDashboard
            [
                'name'          => 'Mary Njeri',
                'email'         => 'tender@teratech.co.tz',
                'password'      => Hash::make('12341234'),
                'role_id'       => 4,
                'department_id' => 3,
                'status'        => 'is_active',
            ],

            // ── Accountant (role_id: 5) → AuthLayout4 / AccountantDashboard
            [
                'name'          => 'Fatuma Hassan',
                'email'         => 'accountant@teratech.co.tz',
                'password'      => Hash::make('12341234'),
                'role_id'       => 5,
                'department_id' => 4,
                'status'        => 'is_active',
            ],

            // ── HR Manager (role_id: 6) → AuthLayout5 / HrDashboard ───────
            [
                'name'          => 'Peter Mushi',
                'email'         => 'hr@teratech.co.tz',
                'password'      => Hash::make('12341234'),
                'role_id'       => 6,
                'department_id' => 1,
                'status'        => 'is_active',
            ],

            // ── Inactive user (for testing locked accounts) ───────────────
            [
                'name'          => 'Test Inactive User',
                'email'         => 'inactive@teratech.co.tz',
                'password'      => Hash::make('12341234'),
                'role_id'       => 3,
                'department_id' => null,
                'status'        => 'inactive',
            ],
        ];

        foreach ($users as $userData) {
            User::firstOrCreate(
                ['email' => $userData['email']],
                $userData
            );
        }

        $this->command->info('Users seeded successfully! Test credentials (all use password: 12341234):');
        $this->command->table(
            ['Role', 'Email', 'Password', 'Dashboard'],
            [
                ['Admin (1)',       'admin@teratech.co.tz',           '12341234', '/dashboard'],
                ['HOD (2)',         'hod.procurement@teratech.co.tz', '12341234', '/hod/dashboard'],
                ['Engineer (3)',    'engineer@teratech.co.tz',        '12341234', '/user/dashboard'],
                ['Tender (4)',      'tender@teratech.co.tz',          '12341234', '/tenders/dashboard'],
                ['Accountant (5)',  'accountant@teratech.co.tz',      '12341234', '/accountDash'],
                ['HR (6)',          'hr@teratech.co.tz',              '12341234', '/hrDash'],
                ['Inactive (3)',    'inactive@teratech.co.tz',        '12341234', 'login blocked'],
            ]
        );
    }
}