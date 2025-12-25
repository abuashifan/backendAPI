<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('customer_payment_allocations', function (Blueprint $table) {
            $table->bigIncrements('id')->comment('Primary key payment allocation');
            $table->unsignedBigInteger('customer_payment_id')->comment('FK ke customer_payments.id');
            $table->unsignedBigInteger('sales_invoice_id')->comment('FK ke sales_invoices.id');
            $table->decimal('allocated_amount', 15, 2)->default(0)->comment('Jumlah alokasi pembayaran ke invoice');
            $table->timestamps();

            $table->foreign('customer_payment_id')->references('id')->on('customer_payments')->onDelete('restrict');
            $table->foreign('sales_invoice_id')->references('id')->on('sales_invoices')->onDelete('restrict');

            $table->unique(['customer_payment_id', 'sales_invoice_id'], 'uniq_receipt_invoice_allocation');
            $table->index('customer_payment_id', 'idx_cpa_payment');
            $table->index('sales_invoice_id', 'idx_cpa_invoice');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('customer_payment_allocations');
    }
};
