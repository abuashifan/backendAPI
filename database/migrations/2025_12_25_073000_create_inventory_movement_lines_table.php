<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('inventory_movement_lines', function (Blueprint $table) {
            $table->bigIncrements('id')->comment('Primary key inventory movement line');
            $table->unsignedBigInteger('inventory_movement_id')->comment('FK ke inventory_movements.id');
            $table->unsignedBigInteger('product_id')->comment('FK ke products.id');

            $table->decimal('qty', 15, 2)->comment('Kuantitas (selalu positif)');
            $table->text('description')->nullable()->comment('Keterangan per baris (opsional)');

            $table->timestamps();

            $table->foreign('inventory_movement_id')->references('id')->on('inventory_movements')->onDelete('restrict');
            $table->foreign('product_id')->references('id')->on('products')->onDelete('restrict');

            $table->index('inventory_movement_id', 'idx_iml_movement');
            $table->index('product_id', 'idx_iml_product');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('inventory_movement_lines');
    }
};
