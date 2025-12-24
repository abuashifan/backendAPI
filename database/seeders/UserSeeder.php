<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;

class UserSeeder extends Seeder
{
    /**
     * Seed base users.
     *
     * Scope: users + direct permission assignment (user-centric).
     * Idempotent: safe to run multiple times.
     */
    public function run(): void
    {
        /** @var PermissionRegistrar $registrar */
        $registrar = app(PermissionRegistrar::class);
        $registrar->forgetCachedPermissions();

        $seedUser = [
            'email' => 'admin@test.com',
            'name' => 'System Admin',
            'password' => 'password123',
        ];

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

        // User-centric model: assign permissions directly to the admin user.
        // Roles (if any) are optional templates and are not required for seeded access.
        $adminPermissions = Permission::query()->pluck('name')->all();
        $user->syncPermissions($adminPermissions);

        $registrar->forgetCachedPermissions();
    }
}
