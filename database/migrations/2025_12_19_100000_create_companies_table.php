<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('companies', function (Blueprint $table) {
            $table->bigIncrements('id')->comment('Primary key perusahaan, digunakan sebagai foreign key di accounting_periods dan chart_of_accounts');
            $table->string('code')->unique()->comment('Kode internal perusahaan (misal: CMP001), digunakan untuk identifikasi business level');
            $table->string('name')->comment('Nama legal perusahaan');
            $table->string('base_currency', 3)->comment('Mata uang utama pembukuan (ISO 4217), contoh: IDR, USD');
            $table->tinyInteger('fiscal_year_start_month')->comment('Bulan awal tahun fiskal (1–12), digunakan untuk generate accounting periods');
            $table->string('timezone')->comment('Timezone perusahaan (misal: Asia/Jakarta), digunakan untuk timestamp akuntansi');
            $table->boolean('is_active')->default(true)->comment('Status aktif perusahaan, jika false transaksi akuntansi harus ditolak');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('companies');
    }
};
