<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->call([PlanSeeder::class, CourseSeeder::class]);

        // First administrator. Set ADMIN_USERNAME and ADMIN_PASSWORD in .env before seeding.
        if (($username = env('ADMIN_USERNAME')) && ($password = env('ADMIN_PASSWORD'))) {
            User::firstOrCreate(['username' => $username], [
                'name' => env('ADMIN_NAME', 'Administrator'),
                'role' => User::ROLE_ADMIN,
                'password' => $password,
            ]);
        }
    }
}
