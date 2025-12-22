<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('journals', function (Blueprint $table) {
            // Phase 2 — Step 19: Audit Flag & Resolution Logic
            // Audit fields are separated from accounting posting and do NOT affect balances.

            $table->string('audit_status')
                ->default('unchecked')
                ->comment('Audit status (does not affect balance): unchecked, checked, issue_flagged, resolved')
                ->after('status');

            $table->text('audit_note')
                ->nullable()
                ->comment('Optional audit note / finding / resolution note (does not affect amounts)')
                ->after('audit_status');

            $table->unsignedBigInteger('audited_by')
                ->nullable()
                ->comment('FK to users.id who performed the latest audit action')
                ->after('audit_note');

            $table->timestamp('audited_at')
                ->nullable()
                ->comment('Timestamp of the latest audit action')
                ->after('audited_by');

            $table->index('audit_status');
            $table->index('audited_by');

            $table->foreign('audited_by')
                ->references('id')
                ->on('users')
                ->onDelete('restrict');
        });
    }

    public function down(): void
    {
        Schema::table('journals', function (Blueprint $table) {
            $table->dropForeign(['audited_by']);
            $table->dropIndex(['audit_status']);
            $table->dropIndex(['audited_by']);

            $table->dropColumn([
                'audit_status',
                'audit_note',
                'audited_by',
                'audited_at',
            ]);
        });
    }
};
