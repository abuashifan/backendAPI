<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('purchase_orders', function (Blueprint $table) {
            $table->bigIncrements('id')->comment('Primary key purchase order');
            $table->unsignedBigInteger('company_id')->comment('FK ke companies.id');
            $table->unsignedBigInteger('vendor_id')->comment('FK ke vendors.id');

            $table->string('po_number')->comment('Nomor PO, unik per company');
            $table->date('po_date')->comment('Tanggal PO');
            $table->date('expected_date')->nullable()->comment('Tanggal perkiraan kedatangan (opsional)');
            $table->enum('status', ['draft', 'approved', 'cancelled'])->default('draft')->comment('Status bisnis PO (bukan accounting)');

            $table->decimal('subtotal', 15, 2)->default(0)->comment('Subtotal PO');
            $table->decimal('tax_amount', 15, 2)->default(0)->comment('Total pajak PO');
            $table->decimal('total_amount', 15, 2)->default(0)->comment('Total PO');
            $table->string('currency_code', 3)->comment('Mata uang PO (ISO 4217)');

            $table->text('notes')->nullable()->comment('Catatan PO (opsional)');

            $table->unsignedBigInteger('created_by')->comment('FK ke users.id (pembuat)');
            $table->unsignedBigInteger('approved_by')->nullable()->comment('FK ke users.id (approver, opsional)');
            $table->timestamp('approved_at')->nullable()->comment('Waktu approval (opsional)');

            $table->timestamps();
            $table->softDeletes();

            $table->foreign('company_id')->references('id')->on('companies')->onDelete('restrict');
            $table->foreign('vendor_id')->references('id')->on('vendors')->onDelete('restrict');
            $table->foreign('created_by')->references('id')->on('users')->onDelete('restrict');
            $table->foreign('approved_by')->references('id')->on('users')->onDelete('restrict');

            $table->unique(['company_id', 'po_number'], 'uniq_po_company_number');
            $table->index('company_id', 'idx_po_company');
            $table->index('vendor_id', 'idx_po_vendor');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('purchase_orders');
    }
};
