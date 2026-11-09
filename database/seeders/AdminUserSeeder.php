<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

/**
 * Bootstraps a single admin account when no users exist.
 * Public registration is disabled in Phase 2.
 */
class AdminUserSeeder extends Seeder
{
    public function run(): void
    {
        if (User::query()->exists()) {
            return;
        }

        $password = env('ADMIN_PASSWORD');

        // Never bootstrap production with the documented local default.
        if (app()->environment('production') && (blank($password) || $password === 'password')) {
            throw new \RuntimeException(
                'Refusing to seed admin in production without a non-default ADMIN_PASSWORD.'
            );
        }

        User::query()->create([
            'name' => env('ADMIN_NAME', 'Admin'),
            'email' => env('ADMIN_EMAIL', 'admin@example.com'),
            'password' => Hash::make($password ?: 'password'),
            'email_verified_at' => now(),
        ]);
    }
}
