<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Models\Role;

class UserSeeder extends Seeder
{
    /**
     * Seed base users for development/testing.
     *
     * Scope: users + role assignment only (no permissions).
     * Idempotent: safe to run multiple times.
     * Rule: each seeded user must have exactly one role.
     */
    public function run(): void
    {
        $guardName = (string) config('auth.defaults.guard', 'web');

        $seedUsers = [
            [
                'email' => 'admin@test.com',
                'name' => 'System Admin',
                'password' => 'password123',
                'role' => 'admin',
            ],
            [
                'email' => 'supervisor@test.com',
                'name' => 'Supervisor User',
                'password' => 'password123',
                'role' => 'supervisor',
            ],
            [
                'email' => 'entry@test.com',
                'name' => 'Entry User',
                'password' => 'password123',
                'role' => 'entry',
            ],
        ];

        foreach ($seedUsers as $seedUser) {
            // Ensure the role exists (RoleSeeder should run first, but keep this safe).
            $role = Role::query()->firstOrCreate([
                'name' => $seedUser['role'],
                'guard_name' => $guardName,
            ]);

            // Do not duplicate users; create only if missing by email.
            $user = User::query()->firstOrNew([
                'email' => $seedUser['email'],
            ]);

            // Keep name consistent for known system actors.
            $user->name = $seedUser['name'];

            // Provide password only when creating (or if password is currently missing).
            if (!$user->exists || empty($user->password)) {
                $user->password = Hash::make($seedUser['password']);
            }

            $user->save();

            // Enforce exactly ONE role per seeded user.
            $user->syncRoles([$role]);
        }
    }
}
