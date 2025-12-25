<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('customer_payments', function (Blueprint $table) {
            $table->bigIncrements('id')->comment('Primary key customer payment (receipt)');
            $table->unsignedBigInteger('company_id')->comment('FK ke companies.id');
            $table->unsignedBigInteger('customer_id')->comment('FK ke customers.id');

            $table->string('receipt_number')->comment('Nomor penerimaan, unik per company');
            $table->date('receipt_date')->comment('Tanggal penerimaan');
            $table->string('receipt_method')->comment('Metode penerimaan (string)');

            $table->decimal('amount', 15, 2)->default(0)->comment('Total penerimaan');
            $table->string('currency_code', 3)->comment('Mata uang penerimaan (ISO 4217)');
            $table->decimal('exchange_rate', 15, 6)->default(1)->comment('Kurs ke base currency (default 1)');

            $table->enum('status', ['draft', 'approved', 'cancelled'])->default('draft')->comment('Status bisnis penerimaan (bukan accounting)');

            $table->text('notes')->nullable()->comment('Catatan penerimaan (opsional)');

            $table->unsignedBigInteger('created_by')->comment('FK ke users.id (pembuat)');
            $table->unsignedBigInteger('approved_by')->nullable()->comment('FK ke users.id (approver, opsional)');
            $table->timestamp('approved_at')->nullable()->comment('Waktu approval (opsional)');

            $table->string('source_type')->nullable()->comment('Tipe sumber (opsional)');
            $table->unsignedBigInteger('source_id')->nullable()->comment('ID sumber (opsional)');

            $table->timestamps();
            $table->softDeletes();

            $table->foreign('company_id')->references('id')->on('companies')->onDelete('restrict');
            $table->foreign('customer_id')->references('id')->on('customers')->onDelete('restrict');
            $table->foreign('created_by')->references('id')->on('users')->onDelete('restrict');
            $table->foreign('approved_by')->references('id')->on('users')->onDelete('restrict');

            $table->unique(['company_id', 'receipt_number'], 'uniq_receipt_company_number');

            $table->index('company_id', 'idx_cp_company');
            $table->index('customer_id', 'idx_cp_customer');
            $table->index(['source_type', 'source_id'], 'idx_cp_source');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('customer_payments');
    }
};
