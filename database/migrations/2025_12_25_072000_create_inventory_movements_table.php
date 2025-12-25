<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('inventory_movements', function (Blueprint $table) {
            $table->bigIncrements('id')->comment('Primary key inventory movement');
            $table->unsignedBigInteger('company_id')->comment('FK ke companies.id');
            $table->unsignedBigInteger('warehouse_id')->comment('FK ke warehouses.id');

            $table->string('movement_number')->comment('Nomor dokumen movement, unik per company');
            $table->date('movement_date')->comment('Tanggal movement');
            $table->enum('type', ['in', 'out'])->comment('Jenis movement: in/out');
            $table->enum('status', ['draft', 'posted'])->default('draft')->comment('Status movement');

            $table->string('reference_type')->nullable()->comment('Tipe sumber (opsional)');
            $table->unsignedBigInteger('reference_id')->nullable()->comment('ID sumber (opsional)');

            $table->text('notes')->nullable()->comment('Catatan movement (opsional)');

            $table->unsignedBigInteger('created_by')->comment('FK ke users.id (pembuat)');
            $table->unsignedBigInteger('posted_by')->nullable()->comment('FK ke users.id (poster, opsional)');
            $table->timestamp('posted_at')->nullable()->comment('Waktu posting movement (opsional)');

            $table->timestamps();
            $table->softDeletes();

            $table->foreign('company_id')->references('id')->on('companies')->onDelete('restrict');
            $table->foreign('warehouse_id')->references('id')->on('warehouses')->onDelete('restrict');
            $table->foreign('created_by')->references('id')->on('users')->onDelete('restrict');
            $table->foreign('posted_by')->references('id')->on('users')->onDelete('restrict');

            $table->unique(['company_id', 'movement_number'], 'uniq_im_company_number');

            $table->index('company_id', 'idx_im_company');
            $table->index('warehouse_id', 'idx_im_warehouse');
            $table->index(['company_id', 'warehouse_id'], 'idx_im_company_warehouse');
            $table->index(['type', 'status'], 'idx_im_type_status');
            $table->index(['reference_type', 'reference_id'], 'idx_im_reference');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('inventory_movements');
    }
};
