<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('vendors', function (Blueprint $table) {
            $table->bigIncrements('id')->comment('Primary key master vendor');
            $table->unsignedBigInteger('company_id')->comment('Foreign key ke companies.id, vendor milik perusahaan mana');
            $table->string('code')->comment('Kode vendor internal, unik per company');
            $table->string('name')->comment('Nama vendor');
            $table->string('tax_id')->nullable()->comment('NPWP / Tax ID vendor (opsional)');
            $table->string('email')->nullable()->comment('Email vendor (opsional)');
            $table->string('phone')->nullable()->comment('Telepon vendor (opsional)');
            $table->text('address')->nullable()->comment('Alamat vendor (opsional)');
            $table->boolean('is_active')->default(true)->comment('Status aktif vendor');
            $table->timestamps();

            $table->foreign('company_id')->references('id')->on('companies')->onDelete('cascade');
            $table->unique(['company_id', 'code'], 'uniq_vendor_company_code');
            $table->index(['company_id', 'is_active'], 'idx_vendor_company_active');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('vendors');
    }
};
