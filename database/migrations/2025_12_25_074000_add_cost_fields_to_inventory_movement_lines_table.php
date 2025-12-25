<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('inventory_movement_lines', function (Blueprint $table) {
            $table->decimal('unit_cost', 15, 6)->nullable()->comment('Biaya per unit (diisi untuk movement IN; dipakai untuk valuation)');
            $table->decimal('valued_unit_cost', 15, 6)->nullable()->comment('Biaya per unit hasil valuation (untuk movement OUT)');
            $table->decimal('valued_total_cost', 15, 2)->nullable()->comment('Total biaya hasil valuation (untuk movement OUT)');

            $table->index('unit_cost', 'idx_iml_unit_cost');
        });
    }

    public function down(): void
    {
        Schema::table('inventory_movement_lines', function (Blueprint $table) {
            $table->dropIndex('idx_iml_unit_cost');
            $table->dropColumn(['unit_cost', 'valued_unit_cost', 'valued_total_cost']);
        });
    }
};
