<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('inventory_cost_allocations', function (Blueprint $table) {
            $table->bigIncrements('id')->comment('Primary key allocation FIFO untuk movement OUT');
            $table->unsignedBigInteger('out_movement_line_id')->comment('FK ke inventory_movement_lines.id (line OUT)');
            $table->unsignedBigInteger('inventory_cost_layer_id')->comment('FK ke inventory_cost_layers.id');

            $table->decimal('qty', 15, 2)->comment('Qty yang diambil dari layer');
            $table->decimal('unit_cost', 15, 6)->comment('Unit cost yang dipakai (copy dari layer)');
            $table->decimal('total_cost', 15, 2)->comment('Total cost (qty * unit_cost)');

            $table->timestamps();

            $table->foreign('out_movement_line_id')->references('id')->on('inventory_movement_lines')->onDelete('restrict');
            $table->foreign('inventory_cost_layer_id')->references('id')->on('inventory_cost_layers')->onDelete('restrict');

            $table->index('out_movement_line_id', 'idx_ica_out_line');
            $table->index('inventory_cost_layer_id', 'idx_ica_layer');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('inventory_cost_allocations');
    }
};
