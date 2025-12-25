<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('audit_events', function (Blueprint $table) {
            $table->bigIncrements('id');

            $table->unsignedBigInteger('user_id')->nullable();
            $table->string('action');
            $table->string('table');
            $table->unsignedBigInteger('record_id');
            $table->json('old_value')->nullable();
            $table->json('new_value')->nullable();
            $table->timestamp('performed_at');

            $table->timestamps();

            $table->index(['table', 'record_id'], 'idx_audit_events_table_record');
            $table->index(['action', 'performed_at'], 'idx_audit_events_action_time');
            $table->index('user_id');

            $table->foreign('user_id')->references('id')->on('users')->onDelete('set null');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('audit_events');
    }
};
