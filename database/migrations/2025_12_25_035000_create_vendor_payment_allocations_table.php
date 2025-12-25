<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('vendor_payment_allocations', function (Blueprint $table) {
            $table->bigIncrements('id')->comment('Primary key payment allocation');
            $table->unsignedBigInteger('vendor_payment_id')->comment('FK ke vendor_payments.id');
            $table->unsignedBigInteger('vendor_invoice_id')->comment('FK ke vendor_invoices.id');
            $table->decimal('allocated_amount', 15, 2)->default(0)->comment('Jumlah alokasi pembayaran ke invoice');
            $table->timestamps();

            $table->foreign('vendor_payment_id')->references('id')->on('vendor_payments')->onDelete('restrict');
            $table->foreign('vendor_invoice_id')->references('id')->on('vendor_invoices')->onDelete('restrict');

            $table->unique(['vendor_payment_id', 'vendor_invoice_id'], 'uniq_payment_invoice_allocation');
            $table->index('vendor_payment_id', 'idx_vpa_payment');
            $table->index('vendor_invoice_id', 'idx_vpa_invoice');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('vendor_payment_allocations');
    }
};
