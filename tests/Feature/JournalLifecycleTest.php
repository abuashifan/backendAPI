<?php

namespace Tests\Feature;

use App\Models\AccountingPeriod;
use App\Models\ChartOfAccount;
use App\Models\Company;
use App\Models\Journal;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class JournalLifecycleTest extends TestCase
{
    use RefreshDatabase;

    private function seedPermissions(): void
    {
        $this->seed(\Database\Seeders\PermissionSeeder::class);
    }

    /**
     * @return array{company:Company, period:AccountingPeriod, cash:ChartOfAccount, revenue:ChartOfAccount}
     */
    private function makeCompanyPeriodAndAccounts(): array
    {
        $company = Company::query()->create([
            'code' => 'CMP001',
            'name' => 'Test Company',
            'base_currency' => 'IDR',
            'fiscal_year_start_month' => 1,
            'timezone' => 'Asia/Jakarta',
            'is_active' => true,
        ]);

        $period = AccountingPeriod::query()->create([
            'company_id' => $company->id,
            'year' => 2025,
            'month' => 12,
            'start_date' => '2025-12-01',
            'end_date' => '2025-12-31',
            'status' => 'open',
            'closed_at' => null,
        ]);

        $cash = ChartOfAccount::query()->create([
            'company_id' => $company->id,
            'code' => '1001',
            'name' => 'Cash',
            'type' => 'asset',
            'normal_balance' => 'debit',
            'parent_id' => null,
            'level' => 2,
            'is_postable' => true,
            'is_active' => true,
        ]);

        $revenue = ChartOfAccount::query()->create([
            'company_id' => $company->id,
            'code' => '4001',
            'name' => 'Revenue',
            'type' => 'income',
            'normal_balance' => 'credit',
            'parent_id' => null,
            'level' => 2,
            'is_postable' => true,
            'is_active' => true,
        ]);

        return compact('company', 'period', 'cash', 'revenue');
    }

    public function test_umkm_user_can_post_without_approval_permission(): void
    {
        $this->seedPermissions();

        $user = User::factory()->create();
        // UMKM: direct permissions, no role dependency.
        $user->syncPermissions([
            'journal.create',
            'journal.post',
            'journal.view',
        ]);

        Sanctum::actingAs($user);

        $ctx = $this->makeCompanyPeriodAndAccounts();

        $create = $this->postJson('/api/journals', [
            'journal' => [
                'journal_number' => 'JRN-0001',
                'company_id' => $ctx['company']->id,
                'period_id' => $ctx['period']->id,
                'journal_date' => '2025-12-25',
                'source_type' => 'manual',
                'source_id' => null,
                'description' => 'UMKM sale',
            ],
            'lines' => [
                ['account_id' => $ctx['cash']->id, 'debit' => 10000, 'credit' => 0],
                ['account_id' => $ctx['revenue']->id, 'debit' => 0, 'credit' => 10000],
            ],
        ]);

        $create->assertCreated();

        $journalId = (int) $create->json('data.id');
        $this->assertGreaterThan(0, $journalId);

        $post = $this->postJson("/api/journals/{$journalId}/post");
        $post->assertOk();

        $journal = Journal::query()->findOrFail($journalId);
        $this->assertSame('posted', $journal->status);
        $this->assertNotNull($journal->approved_at);
        $this->assertSame($user->id, $journal->approved_by);
        $this->assertNotNull($journal->posted_at);
        $this->assertSame($user->id, $journal->posted_by);

        $this->assertDatabaseHas('audit_events', [
            'user_id' => $user->id,
            'action' => 'journal.create',
            'table' => 'journals',
            'record_id' => $journalId,
        ]);

        $this->assertDatabaseHas('audit_events', [
            'user_id' => $user->id,
            'action' => 'journal.post',
            'table' => 'journals',
            'record_id' => $journalId,
        ]);
    }

    public function test_small_team_permissions_are_separate_and_approval_optional(): void
    {
        $this->seedPermissions();

        $creator = User::factory()->create();
        $creator->syncPermissions(['journal.create']);

        $poster = User::factory()->create();
        $poster->syncPermissions(['journal.post']);

        $ctx = $this->makeCompanyPeriodAndAccounts();

        Sanctum::actingAs($creator);

        $create = $this->postJson('/api/journals', [
            'journal' => [
                'journal_number' => 'JRN-0002',
                'company_id' => $ctx['company']->id,
                'period_id' => $ctx['period']->id,
                'journal_date' => '2025-12-25',
                'source_type' => 'manual',
                'source_id' => null,
                'description' => 'Team entry',
            ],
            'lines' => [
                ['account_id' => $ctx['cash']->id, 'debit' => 5000, 'credit' => 0],
                ['account_id' => $ctx['revenue']->id, 'debit' => 0, 'credit' => 5000],
            ],
        ])->assertCreated();

        $journalId = (int) $create->json('data.id');

        // Creator cannot post (no permission)
        $this->postJson("/api/journals/{$journalId}/post")
            ->assertForbidden();

        // Poster can post without needing approve permission
        Sanctum::actingAs($poster);

        $this->postJson("/api/journals/{$journalId}/post")
            ->assertOk();

        $journal = Journal::query()->findOrFail($journalId);
        $this->assertSame('posted', $journal->status);
        $this->assertSame($poster->id, $journal->posted_by);
        $this->assertSame($poster->id, $journal->approved_by);
    }

    public function test_period_close_blocks_create_post_and_reverse(): void
    {
        $this->seedPermissions();

        $user = User::factory()->create();
        $user->syncPermissions([
            'journal.create',
            'journal.post',
            'journal.reverse',
            'period.close',
        ]);

        Sanctum::actingAs($user);

        $ctx = $this->makeCompanyPeriodAndAccounts();

        // Create draft while open
        $create = $this->postJson('/api/journals', [
            'journal' => [
                'journal_number' => 'JRN-0003',
                'company_id' => $ctx['company']->id,
                'period_id' => $ctx['period']->id,
                'journal_date' => '2025-12-25',
                'source_type' => 'manual',
                'source_id' => null,
                'description' => 'Before close',
            ],
            'lines' => [
                ['account_id' => $ctx['cash']->id, 'debit' => 7000, 'credit' => 0],
                ['account_id' => $ctx['revenue']->id, 'debit' => 0, 'credit' => 7000],
            ],
        ])->assertCreated();

        $journalId = (int) $create->json('data.id');

        // Close period
        $this->postJson('/api/periods/' . $ctx['period']->id . '/close')
            ->assertOk();

        // Cannot create in closed period
        $this->postJson('/api/journals', [
            'journal' => [
                'journal_number' => 'JRN-0004',
                'company_id' => $ctx['company']->id,
                'period_id' => $ctx['period']->id,
                'journal_date' => '2025-12-25',
                'source_type' => 'manual',
                'source_id' => null,
                'description' => 'After close',
            ],
            'lines' => [
                ['account_id' => $ctx['cash']->id, 'debit' => 1000, 'credit' => 0],
                ['account_id' => $ctx['revenue']->id, 'debit' => 0, 'credit' => 1000],
            ],
        ])->assertStatus(409);

        // Cannot post in closed period
        $this->postJson("/api/journals/{$journalId}/post")
            ->assertStatus(409);

        // Force a posted journal in this closed period to test reverse blocking
        Journal::query()->whereKey($journalId)->update([
            'status' => 'posted',
            'posted_by' => $user->id,
            'posted_at' => now(),
            'approved_by' => $user->id,
            'approved_at' => now(),
        ]);

        $this->postJson("/api/journals/{$journalId}/reverse")
            ->assertStatus(409);
    }
}
