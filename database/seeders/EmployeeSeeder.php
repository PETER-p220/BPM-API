<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Employee;
use Illuminate\Support\Facades\Hash;

class EmployeeSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $employees = [
            [
                'first_name' => 'John',
                'last_name' => 'Smith',
                'email' => 'john.smith@company.com',
                'phone' => '+1234567890',
                'department' => 'IT',
                'position' => 'Senior Developer',
                'salary' => 75000.00,
                'hire_date' => '2022-01-15',
                'status' => 'active',
                'address' => '123 Main St, City, State',
                'emergency_contact' => 'Jane Smith',
                'emergency_phone' => '+1234567891',
                'birth_date' => '1990-05-20',
                'gender' => 'male',
                'national_id' => 'ID123456',
                'bank_account' => 'ACC123456',
                'bank_name' => 'First National Bank',
                'notes' => 'Experienced developer with expertise in Vue.js and Laravel'
            ],
            [
                'first_name' => 'Sarah',
                'last_name' => 'Johnson',
                'email' => 'sarah.johnson@company.com',
                'phone' => '+1234567892',
                'department' => 'HR',
                'position' => 'HR Manager',
                'salary' => 65000.00,
                'hire_date' => '2021-03-10',
                'status' => 'active',
                'address' => '456 Oak Ave, City, State',
                'emergency_contact' => 'Mike Johnson',
                'emergency_phone' => '+1234567893',
                'birth_date' => '1985-08-15',
                'gender' => 'female',
                'national_id' => 'ID789012',
                'bank_account' => 'ACC789012',
                'bank_name' => 'City Bank',
                'notes' => 'Dedicated HR professional with 8+ years of experience'
            ],
            [
                'first_name' => 'Michael',
                'last_name' => 'Brown',
                'email' => 'michael.brown@company.com',
                'phone' => '+1234567894',
                'department' => 'Finance',
                'position' => 'Financial Analyst',
                'salary' => 70000.00,
                'hire_date' => '2022-06-20',
                'status' => 'active',
                'address' => '789 Pine Rd, City, State',
                'emergency_contact' => 'Lisa Brown',
                'emergency_phone' => '+1234567895',
                'birth_date' => '1988-12-10',
                'gender' => 'male',
                'national_id' => 'ID345678',
                'bank_account' => 'ACC345678',
                'bank_name' => 'National Trust Bank',
                'notes' => 'Detail-oriented financial analyst with CPA certification'
            ],
            [
                'first_name' => 'Emily',
                'last_name' => 'Davis',
                'email' => 'emily.davis@company.com',
                'phone' => '+1234567896',
                'department' => 'Operations',
                'position' => 'Operations Manager',
                'salary' => 68000.00,
                'hire_date' => '2021-09-15',
                'status' => 'on_leave',
                'address' => '321 Elm St, City, State',
                'emergency_contact' => 'Robert Davis',
                'emergency_phone' => '+1234567897',
                'birth_date' => '1987-04-25',
                'gender' => 'female',
                'national_id' => 'ID901234',
                'bank_account' => 'ACC901234',
                'bank_name' => 'Regional Bank',
                'notes' => 'Currently on maternity leave, expected return Q2 2024'
            ],
            [
                'first_name' => 'David',
                'last_name' => 'Wilson',
                'email' => 'david.wilson@company.com',
                'phone' => '+1234567898',
                'department' => 'IT',
                'position' => 'System Administrator',
                'salary' => 62000.00,
                'hire_date' => '2023-02-01',
                'status' => 'active',
                'address' => '654 Maple Dr, City, State',
                'emergency_contact' => 'Susan Wilson',
                'emergency_phone' => '+1234567899',
                'birth_date' => '1992-07-30',
                'gender' => 'male',
                'national_id' => 'ID567890',
                'bank_account' => 'ACC567890',
                'bank_name' => 'Tech Credit Union',
                'notes' => 'System administration expert with cloud infrastructure experience'
            ],
            [
                'first_name' => 'Lisa',
                'last_name' => 'Anderson',
                'email' => 'lisa.anderson@company.com',
                'phone' => '+1234567800',
                'department' => 'HR',
                'position' => 'Recruitment Specialist',
                'salary' => 55000.00,
                'hire_date' => '2023-04-10',
                'status' => 'active',
                'address' => '987 Cedar Ln, City, State',
                'emergency_contact' => 'Tom Anderson',
                'emergency_phone' => '+1234567801',
                'birth_date' => '1991-11-18',
                'gender' => 'female',
                'national_id' => 'ID234567',
                'bank_account' => 'ACC234567',
                'bank_name' => 'Community Bank',
                'notes' => 'Specialized in tech recruitment and talent acquisition'
            ],
            [
                'first_name' => 'James',
                'last_name' => 'Taylor',
                'email' => 'james.taylor@company.com',
                'phone' => '+1234567802',
                'department' => 'Finance',
                'position' => 'Accountant',
                'salary' => 58000.00,
                'hire_date' => '2022-11-20',
                'status' => 'inactive',
                'address' => '147 Birch Way, City, State',
                'emergency_contact' => 'Mary Taylor',
                'emergency_phone' => '+1234567803',
                'birth_date' => '1989-03-12',
                'gender' => 'male',
                'national_id' => 'ID890123',
                'bank_account' => 'ACC890123',
                'bank_name' => 'First National Bank',
                'notes' => 'Contract ended, available for rehire'
            ],
            [
                'first_name' => 'Jennifer',
                'last_name' => 'Martinez',
                'email' => 'jennifer.martinez@company.com',
                'phone' => '+1234567804',
                'department' => 'Operations',
                'position' => 'Project Coordinator',
                'salary' => 52000.00,
                'hire_date' => '2023-01-15',
                'status' => 'active',
                'address' => '258 Spruce St, City, State',
                'emergency_contact' => 'Carlos Martinez',
                'emergency_phone' => '+1234567805',
                'birth_date' => '1993-09-08',
                'gender' => 'female',
                'national_id' => 'ID012345',
                'bank_account' => 'ACC012345',
                'bank_name' => 'Metro Bank',
                'notes' => 'Excellent organizational and communication skills'
            ]
        ];

        foreach ($employees as $employee) {
            Employee::create($employee);
        }
    }
}
