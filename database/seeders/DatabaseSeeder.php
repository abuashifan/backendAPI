<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        // User::factory(10)->create();

        $user = User::query()->firstOrCreate(
            ['email' => 'test@example.com'],
            [
                'name' => 'Test User',
                'password' => 'password',
            ]
        );

        if ($user->name !== 'Test User') {
            $user->forceFill(['name' => 'Test User'])->save();
        }

        DB::table('companies')->updateOrInsert(
            ['code' => 'CMP-TEST'],
            [
                'name' => 'Test Company',
                'base_currency' => 'USD',
                'fiscal_year_start_month' => 1,
                'timezone' => 'UTC',
                'is_active' => true,
                'updated_at' => now(),
                'created_at' => now(),
            ]
        );

        $this->call([
            AccountingPeriodSeeder::class,
            ChartOfAccountsSeeder::class,
        ]);
    }
}
