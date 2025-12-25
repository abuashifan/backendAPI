<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('customers', function (Blueprint $table) {
            $table->bigIncrements('id')->comment('Primary key master customer');
            $table->unsignedBigInteger('company_id')->comment('Foreign key ke companies.id, customer milik perusahaan mana');
            $table->string('code')->comment('Kode customer internal, unik per company');
            $table->string('name')->comment('Nama customer');
            $table->string('tax_id')->nullable()->comment('NPWP / Tax ID customer (opsional)');
            $table->string('email')->nullable()->comment('Email customer (opsional)');
            $table->string('phone')->nullable()->comment('Telepon customer (opsional)');
            $table->text('address')->nullable()->comment('Alamat customer (opsional)');
            $table->boolean('is_active')->default(true)->comment('Status aktif customer');
            $table->timestamps();

            $table->foreign('company_id')->references('id')->on('companies')->onDelete('cascade');
            $table->unique(['company_id', 'code'], 'uniq_customer_company_code');
            $table->index(['company_id', 'is_active'], 'idx_customer_company_active');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('customers');
    }
};
