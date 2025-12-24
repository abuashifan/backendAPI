<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Role;

class RoleSeeder extends Seeder
{
    /**
     * Seed base system roles (optional templates).
     *
     * Scope: roles only (no permissions).
     * Idempotent: safe to run multiple times.
     */
    public function run(): void
    {
        // Use the app's default auth guard to avoid hard-coding guard assumptions.
        $guardName = (string) config('auth.defaults.guard', 'web');

        $roles = [
            'admin',
            'supervisor',
            'entry',
        ];

        foreach ($roles as $roleName) {
            // Create the role if missing; do nothing if it already exists.
            Role::query()->firstOrCreate([
                'name' => $roleName,
                'guard_name' => $guardName,
            ]);
        }
    }
}
