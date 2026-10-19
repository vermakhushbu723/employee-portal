<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

/**
 * Demo data. Order matters: leave → attendance (skips leave days)
 * → work (uses attendance days for call history).
 *
 * Logins (password: "password"):
 *   ADM001 Super Admin · HR001 HR · MGR001 Manager · EMP001–EMP003 Employees
 */
class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->call([
            OrganisationSeeder::class,
            LeaveSeeder::class,
            AttendanceSeeder::class,
            WorkSeeder::class,
        ]);
    }
}
