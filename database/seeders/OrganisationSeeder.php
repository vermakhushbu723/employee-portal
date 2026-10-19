<?php

namespace Database\Seeders;

use App\Enums\Role;
use App\Models\Department;
use App\Models\Shift;
use App\Models\User;
use Illuminate\Database\Seeder;

class OrganisationSeeder extends Seeder
{
    public function run(): void
    {
        $sales = Department::create(['name' => 'Telecalling & Sales', 'code' => 'TSL']);
        $hr = Department::create(['name' => 'Human Resources', 'code' => 'HRD']);
        Department::create(['name' => 'Lead Generation', 'code' => 'LGN']);
        $admin = Department::create(['name' => 'Administration', 'code' => 'ADM']);

        $general = Shift::create([
            'name' => 'General', 'start_time' => '10:00:00', 'end_time' => '19:00:00',
            'required_minutes' => 480, 'break_allowed_minutes' => 60, 'grace_minutes' => 15, 'checkin_opens_minutes' => 5, 'is_default' => true,
        ]);
        Shift::create([
            'name' => 'Morning', 'start_time' => '07:00:00', 'end_time' => '16:00:00',
            'required_minutes' => 480, 'break_allowed_minutes' => 60, 'grace_minutes' => 10,
        ]);

        $this->person('ADM001', 'Admin User', Role::SuperAdmin, 'admin@portal.test', '9000000001', [
            'department_id' => $admin->id, 'shift_id' => $general->id, 'designation' => 'System Administrator', 'work_type' => 'office',
        ]);

        $this->person('HR001', 'Neha Verma', Role::Hr, 'hr@portal.test', '9000000002', [
            'department_id' => $hr->id, 'shift_id' => $general->id, 'designation' => 'HR Executive', 'work_type' => 'office',
        ]);

        $manager = $this->person('MGR001', 'Rohit Sharma', Role::Manager, 'rohit@portal.test', '9000000003', [
            'department_id' => $sales->id, 'shift_id' => $general->id, 'designation' => 'Team Manager',
        ]);

        foreach ([
            ['EMP001', 'Riya Sharma', 'riya@portal.test', '9876500001'],
            ['EMP002', 'Aman Gupta', 'aman@portal.test', '9876500002'],
            ['EMP003', 'Sneha Patel', 'sneha@portal.test', '9876500003'],
        ] as [$code, $name, $email, $mobile]) {
            $this->person($code, $name, Role::Employee, $email, $mobile, [
                'department_id' => $sales->id,
                'shift_id' => $general->id,
                'manager_id' => $manager->id,
                'designation' => 'Telecaller',
                'monthly_salary' => 15000,
            ]);
        }
    }

    private function person(string $code, string $name, Role $role, string $email, string $mobile, array $profile): User
    {
        $user = User::create([
            'employee_code' => $code,
            'name' => $name,
            'email' => $email,
            'mobile' => $mobile,
            'role' => $role,
            'password' => 'password',
        ]);

        $joining = now()->subMonths(8)->startOfDay();

        $user->employee()->create([
            'work_type' => 'work_from_home',
            'training_from' => $joining->copy()->subDays(14)->toDateString(),
            'training_to' => $joining->copy()->subDay()->toDateString(),
            'joining_date' => $joining->toDateString(),
            ...$profile,
        ]);

        return $user;
    }
}
