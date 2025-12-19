<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('chart_of_accounts', function (Blueprint $table) {
            $table->bigIncrements('id')->comment('Primary key akun');
            $table->unsignedBigInteger('company_id')->comment('Foreign key ke companies.id, setiap akun terikat ke satu perusahaan');
            $table->string('code')->comment('Kode akun (misal: 1001, 4001), digunakan untuk reporting & journal reference');
            $table->string('name')->comment('Nama akun (Kas, Piutang Usaha, dll)');
            $table->enum('type', ['asset', 'liability', 'equity', 'income', 'expense'])->comment('Klasifikasi akun akuntansi, digunakan untuk laporan keuangan');
            $table->enum('normal_balance', ['debit', 'credit'])->comment('Saldo normal akun, digunakan untuk validasi journal & reporting');
            $table->unsignedBigInteger('parent_id')->nullable()->comment('Self reference ke chart_of_accounts.id, digunakan untuk struktur hierarki akun');
            $table->tinyInteger('level')->comment('Level kedalaman akun dalam hierarchy, contoh: 1 = header, 2 = detail');
            $table->boolean('is_postable')->default(true)->comment('Menandakan apakah akun boleh dipakai di journal, header account biasanya false');
            $table->boolean('is_active')->default(true)->comment('Status aktif akun, jika false tidak boleh dipakai transaksi');
            $table->timestamps();

            $table->foreign('company_id')->references('id')->on('companies')->onDelete('cascade');
            $table->foreign('parent_id')->references('id')->on('chart_of_accounts')->onDelete('set null');
            $table->index(['company_id', 'code'], 'idx_company_code');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('chart_of_accounts');
    }
};
