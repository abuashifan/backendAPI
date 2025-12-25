<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('inventory_cost_layers', function (Blueprint $table) {
            $table->bigIncrements('id')->comment('Primary key inventory cost layer (FIFO)');
            $table->unsignedBigInteger('company_id')->comment('FK ke companies.id');
            $table->unsignedBigInteger('warehouse_id')->comment('FK ke warehouses.id');
            $table->unsignedBigInteger('product_id')->comment('FK ke products.id');

            $table->unsignedBigInteger('source_movement_line_id')->comment('FK ke inventory_movement_lines.id (line IN)');

            $table->date('received_at')->comment('Tanggal layer diterima');
            $table->decimal('unit_cost', 15, 6)->comment('Biaya per unit layer');
            $table->decimal('qty_received', 15, 2)->comment('Qty diterima');
            $table->decimal('qty_remaining', 15, 2)->comment('Qty tersisa untuk konsumsi FIFO');

            $table->timestamps();

            $table->foreign('company_id')->references('id')->on('companies')->onDelete('restrict');
            $table->foreign('warehouse_id')->references('id')->on('warehouses')->onDelete('restrict');
            $table->foreign('product_id')->references('id')->on('products')->onDelete('restrict');
            $table->foreign('source_movement_line_id')->references('id')->on('inventory_movement_lines')->onDelete('restrict');

            $table->unique('source_movement_line_id', 'uniq_icl_source_line');

            $table->index(['company_id', 'warehouse_id', 'product_id'], 'idx_icl_scope');
            $table->index(['company_id', 'warehouse_id', 'product_id', 'received_at'], 'idx_icl_scope_received');
            $table->index('qty_remaining', 'idx_icl_remaining');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('inventory_cost_layers');
    }
};
