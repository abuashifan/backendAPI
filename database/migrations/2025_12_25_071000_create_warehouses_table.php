<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('warehouses', function (Blueprint $table) {
            $table->bigIncrements('id')->comment('Primary key master warehouse');
            $table->unsignedBigInteger('company_id')->comment('Foreign key ke companies.id, warehouse milik perusahaan mana');
            $table->string('code')->comment('Kode warehouse internal, unik per company');
            $table->string('name')->comment('Nama warehouse');
            $table->text('address')->nullable()->comment('Alamat warehouse (opsional)');
            $table->boolean('is_active')->default(true)->comment('Status aktif warehouse');
            $table->timestamps();

            $table->foreign('company_id')->references('id')->on('companies')->onDelete('cascade');
            $table->unique(['company_id', 'code'], 'uniq_warehouse_company_code');
            $table->index(['company_id', 'is_active'], 'idx_warehouse_company_active');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('warehouses');
    }
};
