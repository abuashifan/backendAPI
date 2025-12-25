<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('vendor_payments', function (Blueprint $table) {
            $table->unsignedBigInteger('posted_by')->nullable()->comment('FK ke users.id (poster, opsional)');
            $table->timestamp('posted_at')->nullable()->comment('Waktu posting (opsional)');

            $table->foreign('posted_by')->references('id')->on('users')->onDelete('restrict');

            $table->index('posted_by', 'idx_vp_posted_by');
            $table->index('posted_at', 'idx_vp_posted_at');
        });
    }

    public function down(): void
    {
        Schema::table('vendor_payments', function (Blueprint $table) {
            $table->dropForeign(['posted_by']);
            $table->dropIndex('idx_vp_posted_by');
            $table->dropIndex('idx_vp_posted_at');
            $table->dropColumn(['posted_by', 'posted_at']);
        });
    }
};
