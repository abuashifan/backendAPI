<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('journals', function (Blueprint $table) {
            $table->unsignedBigInteger('approved_by')
                ->nullable()
                ->after('created_by');

            $table->timestamp('approved_at')
                ->nullable()
                ->after('approved_by');

            $table->unsignedBigInteger('posted_by')
                ->nullable()
                ->after('approved_at');

            $table->unsignedBigInteger('reversed_by')
                ->nullable()
                ->after('posted_by');

            $table->timestamp('reversed_at')
                ->nullable()
                ->after('reversed_by');

            $table->unsignedBigInteger('reversal_of_journal_id')
                ->nullable()
                ->comment('If set, this journal is a reversal of the referenced posted journal')
                ->after('reversed_at');

            $table->index('approved_by');
            $table->index('posted_by');
            $table->index('reversed_by');
            $table->index('reversal_of_journal_id');

            $table->foreign('approved_by')->references('id')->on('users')->onDelete('restrict');
            $table->foreign('posted_by')->references('id')->on('users')->onDelete('restrict');
            $table->foreign('reversed_by')->references('id')->on('users')->onDelete('restrict');
            $table->foreign('reversal_of_journal_id')->references('id')->on('journals')->onDelete('restrict');
        });
    }

    public function down(): void
    {
        Schema::table('journals', function (Blueprint $table) {
            $table->dropForeign(['approved_by']);
            $table->dropForeign(['posted_by']);
            $table->dropForeign(['reversed_by']);
            $table->dropForeign(['reversal_of_journal_id']);

            $table->dropIndex(['approved_by']);
            $table->dropIndex(['posted_by']);
            $table->dropIndex(['reversed_by']);
            $table->dropIndex(['reversal_of_journal_id']);

            $table->dropColumn([
                'approved_by',
                'approved_at',
                'posted_by',
                'reversed_by',
                'reversed_at',
                'reversal_of_journal_id',
            ]);
        });
    }
};
