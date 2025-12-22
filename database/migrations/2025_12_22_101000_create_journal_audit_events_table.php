<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        // Phase 2 — Step 19: Audit Flag & Resolution Logic
        // This table stores an immutable history of audit actions on journals.
        // It is NOT an approval workflow and does NOT affect accounting balances.

        Schema::create('journal_audit_events', function (Blueprint $table) {
            $table->bigIncrements('id');

            $table->unsignedBigInteger('journal_id');

            // Action is the human meaning; status change is stored explicitly too.
            // Values expected: checked, issue_flagged, resolved
            $table->string('action');

            $table->string('previous_audit_status');
            $table->string('new_audit_status');

            $table->text('note')->nullable();

            $table->unsignedBigInteger('performed_by');
            $table->timestamp('performed_at');

            $table->timestamps();

            $table->index(['journal_id', 'performed_at'], 'idx_journal_audit_events_journal_time');
            $table->index('new_audit_status');
            $table->index('performed_by');

            $table->foreign('journal_id')->references('id')->on('journals')->onDelete('cascade');
            $table->foreign('performed_by')->references('id')->on('users')->onDelete('restrict');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('journal_audit_events');
    }
};
