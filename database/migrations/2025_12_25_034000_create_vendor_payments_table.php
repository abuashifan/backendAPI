<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('vendor_payments', function (Blueprint $table) {
            $table->bigIncrements('id')->comment('Primary key vendor payment');
            $table->unsignedBigInteger('company_id')->comment('FK ke companies.id');
            $table->unsignedBigInteger('vendor_id')->comment('FK ke vendors.id');

            $table->string('payment_number')->comment('Nomor pembayaran, unik per company');
            $table->date('payment_date')->comment('Tanggal pembayaran');
            $table->string('payment_method')->comment('Metode pembayaran (string)');

            $table->decimal('amount', 15, 2)->default(0)->comment('Total pembayaran');
            $table->string('currency_code', 3)->comment('Mata uang pembayaran (ISO 4217)');
            $table->decimal('exchange_rate', 15, 6)->default(1)->comment('Kurs ke base currency (default 1)');

            $table->enum('status', ['draft', 'approved', 'cancelled'])->default('draft')->comment('Status bisnis pembayaran (bukan accounting)');

            $table->text('notes')->nullable()->comment('Catatan pembayaran (opsional)');

            $table->unsignedBigInteger('created_by')->comment('FK ke users.id (pembuat)');
            $table->unsignedBigInteger('approved_by')->nullable()->comment('FK ke users.id (approver, opsional)');
            $table->timestamp('approved_at')->nullable()->comment('Waktu approval (opsional)');

            $table->string('source_type')->nullable()->comment('Tipe sumber (opsional)');
            $table->unsignedBigInteger('source_id')->nullable()->comment('ID sumber (opsional)');

            $table->timestamps();
            $table->softDeletes();

            $table->foreign('company_id')->references('id')->on('companies')->onDelete('restrict');
            $table->foreign('vendor_id')->references('id')->on('vendors')->onDelete('restrict');
            $table->foreign('created_by')->references('id')->on('users')->onDelete('restrict');
            $table->foreign('approved_by')->references('id')->on('users')->onDelete('restrict');

            $table->unique(['company_id', 'payment_number'], 'uniq_payment_company_number');

            $table->index('company_id', 'idx_vp_company');
            $table->index('vendor_id', 'idx_vp_vendor');
            $table->index(['source_type', 'source_id'], 'idx_vp_source');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('vendor_payments');
    }
};
