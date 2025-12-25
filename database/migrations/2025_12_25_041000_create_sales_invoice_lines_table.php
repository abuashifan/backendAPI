<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('sales_invoice_lines', function (Blueprint $table) {
            $table->bigIncrements('id')->comment('Primary key sales invoice line');
            $table->unsignedBigInteger('sales_invoice_id')->comment('FK ke sales_invoices.id');

            // product_id akan direlasikan ke products pada Phase 4 (schema belum ada pada Step 29)
            $table->unsignedBigInteger('product_id')->nullable()->comment('FK ke products.id (nullable; Phase 4)');

            $table->string('description')->comment('Deskripsi item/barang/jasa');
            $table->decimal('qty', 15, 2)->comment('Kuantitas');
            $table->decimal('unit_price', 15, 2)->comment('Harga satuan');
            $table->decimal('line_total', 15, 2)->comment('Total baris (qty * unit_price)');

            // tax_id akan direlasikan ke tax master bila ada (schema belum ada pada Step 29)
            $table->unsignedBigInteger('tax_id')->nullable()->comment('FK ke taxes.id (nullable; future)');

            $table->timestamps();

            $table->foreign('sales_invoice_id')->references('id')->on('sales_invoices')->onDelete('restrict');

            $table->index('sales_invoice_id', 'idx_si_line_invoice');
            $table->index('product_id', 'idx_si_line_product');
            $table->index('tax_id', 'idx_si_line_tax');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sales_invoice_lines');
    }
};
