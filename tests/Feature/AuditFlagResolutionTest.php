<?php

namespace Tests\Feature;

use App\Models\AccountingPeriod;
use App\Models\Company;
use App\Models\Journal;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class AuditFlagResolutionTest extends TestCase
{
    use RefreshDatabase;

    private function seedPermissions(): void
    {
        $this->seed(\Database\Seeders\PermissionSeeder::class);
    }

    private function makeJournal(int $createdByUserId): Journal
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

        return Journal::query()->create([
            'journal_number' => 'JRN-0001',
            'company_id' => $company->id,
            'period_id' => $period->id,
            'journal_date' => '2025-12-22',
            'source_type' => 'manual',
            'source_id' => null,
            'description' => 'Test journal',
            'status' => 'posted',
            'created_by' => $createdByUserId,
            'posted_at' => now(),
            'audit_status' => 'unchecked',
        ]);
    }

    public function test_auditor_can_flag_issue_but_cannot_resolve(): void
    {
        $this->seedPermissions();

        $auditor = User::factory()->create();
        $auditor->syncPermissions(['audit.flagIssue', 'audit.viewStatus']);

        $journalCreator = User::factory()->create();

        $journal = $this->makeJournal($journalCreator->id);

        Sanctum::actingAs($auditor);

        $flagResponse = $this->postJson("/api/journals/{$journal->id}/audit/flag", [
            'audit_note' => 'Suspicious supporting document',
        ]);

        $flagResponse->assertOk();

        $journal->refresh();
        $this->assertSame('issue_flagged', $journal->audit_status);
        $this->assertSame($auditor->id, $journal->audited_by);
        $this->assertNotNull($journal->audited_at);

        $this->assertDatabaseHas('journal_audit_events', [
            'journal_id' => $journal->id,
            'action' => 'issue_flagged',
            'new_audit_status' => 'issue_flagged',
            'performed_by' => $auditor->id,
        ]);

        $resolveResponse = $this->postJson("/api/journals/{$journal->id}/audit/resolve", [
            'audit_note' => 'Resolved by auditor (should not be allowed)',
        ]);

        $resolveResponse->assertForbidden();
    }

    public function test_admin_can_resolve_issue(): void
    {
        $this->seedPermissions();

        $admin = User::factory()->create();
        $admin->syncPermissions(['audit.flagIssue', 'audit.resolve', 'audit.viewStatus']);

        $journalCreator = User::factory()->create();

        $journal = $this->makeJournal($journalCreator->id);

        Sanctum::actingAs($admin);

        $this->postJson("/api/journals/{$journal->id}/audit/flag", [
            'audit_note' => 'Needs clarification',
        ])->assertOk();

        $this->postJson("/api/journals/{$journal->id}/audit/resolve", [
            'audit_note' => 'Clarified and resolved',
        ])->assertOk();

        $journal->refresh();
        $this->assertSame('resolved', $journal->audit_status);

        $this->assertDatabaseHas('journal_audit_events', [
            'journal_id' => $journal->id,
            'action' => 'resolved',
            'new_audit_status' => 'resolved',
            'performed_by' => $admin->id,
        ]);

        // Sanity: audit must not change accounting status/amounts
        $this->assertSame('posted', $journal->status);
    }

    public function test_staff_cannot_audit(): void
    {
        $this->seedPermissions();

        $staff = User::factory()->create();

        $journal = $this->makeJournal($staff->id);

        Sanctum::actingAs($staff);

        $this->postJson("/api/journals/{$journal->id}/audit/check")
            ->assertForbidden();

        $this->postJson("/api/journals/{$journal->id}/audit/flag")
            ->assertForbidden();

        $this->postJson("/api/journals/{$journal->id}/audit/resolve")
            ->assertForbidden();
    }

    public function test_issues_endpoint_filters_by_status(): void
    {
        $this->seedPermissions();

        $admin = User::factory()->create();
        $admin->syncPermissions(['audit.flagIssue', 'audit.resolve', 'audit.viewStatus']);

        $journalCreator = User::factory()->create();

        $journal = $this->makeJournal($journalCreator->id);

        Sanctum::actingAs($admin);

        $this->postJson("/api/journals/{$journal->id}/audit/flag", [
            'audit_note' => 'Issue',
        ])->assertOk();

        $this->getJson('/api/audits/issues?audit_status=issue_flagged')
            ->assertOk()
            ->assertJsonPath('data.0.id', $journal->id);

        $this->getJson('/api/audits/issues?audit_status=resolved')
            ->assertOk()
            ->assertJsonCount(0, 'data');

        $this->postJson("/api/journals/{$journal->id}/audit/resolve", [
            'audit_note' => 'Resolved',
        ])->assertOk();

        $this->getJson('/api/audits/issues?audit_status=resolved')
            ->assertOk()
            ->assertJsonPath('data.0.id', $journal->id);
    }
}
