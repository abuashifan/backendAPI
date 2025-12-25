<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('products', function (Blueprint $table) {
            $table->bigIncrements('id')->comment('Primary key master product');
            $table->unsignedBigInteger('company_id')->comment('Foreign key ke companies.id, product milik perusahaan mana');
            $table->string('code')->comment('Kode product internal, unik per company');
            $table->string('name')->comment('Nama product');
            $table->string('type')->comment('Tipe product: stock_item atau service');
            $table->string('uom')->comment('Unit of measure (misal: pcs, kg)');
            $table->boolean('is_active')->default(true)->comment('Status aktif product');
            $table->timestamps();

            $table->foreign('company_id')->references('id')->on('companies')->onDelete('cascade');
            $table->unique(['company_id', 'code'], 'uniq_product_company_code');
            $table->index(['company_id', 'is_active'], 'idx_product_company_active');
            $table->index(['company_id', 'type'], 'idx_product_company_type');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('products');
    }
};
