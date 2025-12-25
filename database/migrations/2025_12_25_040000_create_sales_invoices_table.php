<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('sales_invoices', function (Blueprint $table) {
            $table->bigIncrements('id')->comment('Primary key sales invoice (AR)');
            $table->unsignedBigInteger('company_id')->comment('FK ke companies.id');
            $table->unsignedBigInteger('customer_id')->comment('FK ke customers.id');

            $table->string('invoice_number')->comment('Nomor invoice, unik per company');
            $table->date('invoice_date')->comment('Tanggal invoice');
            $table->date('due_date')->comment('Tanggal jatuh tempo');

            $table->enum('status', ['draft', 'approved', 'cancelled', 'partial', 'paid'])->default('draft')->comment('Status bisnis invoice (bukan accounting)');

            $table->decimal('subtotal', 15, 2)->default(0)->comment('Subtotal invoice');
            $table->decimal('tax_amount', 15, 2)->default(0)->comment('Total pajak invoice');
            $table->decimal('total_amount', 15, 2)->default(0)->comment('Total invoice');

            $table->string('currency_code', 3)->comment('Mata uang invoice (ISO 4217)');
            $table->decimal('exchange_rate', 15, 6)->default(1)->comment('Kurs ke base currency (default 1)');

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

            $table->unique(['company_id', 'invoice_number'], 'uniq_si_company_number');

            $table->index('company_id', 'idx_si_company');
            $table->index('customer_id', 'idx_si_customer');
            $table->index('invoice_number', 'idx_si_invoice_number');
            $table->index(['source_type', 'source_id'], 'idx_si_source');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sales_invoices');
    }
};
