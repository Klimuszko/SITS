<?php

namespace Database\Seeders;

use App\Enums\Role;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class SuperAdminSeeder extends Seeder
{
    public function run(): void
    {
        $email = env('SUPERADMIN_EMAIL', 'admin@serwisit.local');

        // firstOrCreate – nie nadpisujemy hasła przy kolejnych uruchomieniach.
        User::firstOrCreate(
            ['email' => $email],
            [
                'name' => env('SUPERADMIN_NAME', 'Super Admin'),
                'password' => Hash::make(env('SUPERADMIN_PASSWORD', 'Admin12345!')),
                'role' => Role::SuperAdmin,
                'is_active' => true,
                'email_verified_at' => now(),
            ],
        );
    }
}
