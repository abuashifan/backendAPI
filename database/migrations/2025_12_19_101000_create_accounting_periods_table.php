<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('accounting_periods', function (Blueprint $table) {
            $table->bigIncrements('id')->comment('Primary key periode akuntansi');
            $table->unsignedBigInteger('company_id')->comment('Foreign key ke companies.id, menandakan periode ini milik perusahaan mana');
            $table->integer('year')->comment('Tahun fiskal periode, contoh: 2025');
            $table->tinyInteger('month')->comment('Bulan periode (1–12), digunakan untuk pembukuan bulanan');
            $table->date('start_date')->comment('Tanggal awal periode, digunakan untuk validasi tanggal journal');
            $table->date('end_date')->comment('Tanggal akhir periode, digunakan untuk validasi dan closing');
            $table->enum('status', ['open', 'closed'])->default('open')->comment('Status periode akuntansi: open = boleh posting journal, closed = journal terkunci');
            $table->timestamp('closed_at')->nullable()->comment('Waktu periode ditutup, digunakan untuk audit trail');
            $table->timestamps();

            $table->foreign('company_id')->references('id')->on('companies')->onDelete('cascade');
            $table->index(['company_id', 'year', 'month'], 'idx_company_year_month');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('accounting_periods');
    }
};
