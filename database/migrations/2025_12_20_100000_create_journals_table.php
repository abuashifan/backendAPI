<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('journals', function (Blueprint $table) {
            $table->bigIncrements('id')->comment('Primary key jurnal, direferensikan oleh journal_lines.journal_id');
            $table->string('journal_number')->comment('Nomor unik jurnal per company, digunakan untuk audit, tracing, dan reporting');
            $table->unsignedBigInteger('company_id')->comment('Foreign key ke companies.id, menandakan jurnal milik perusahaan mana');
            $table->unsignedBigInteger('period_id')->comment('Foreign key ke accounting_periods.id, mengunci jurnal ke satu periode akuntansi');
            $table->date('journal_date')->comment('Tanggal efektif akuntansi, digunakan untuk laporan keuangan');
            $table->string('source_type')->comment('Jenis sumber jurnal, contoh: manual, sales_invoice, vendor_invoice, inventory, asset');
            $table->unsignedBigInteger('source_id')->nullable()->comment('ID dokumen sumber (invoice, asset, dll), nullable untuk jurnal manual');
            $table->text('description')->comment('Narasi jurnal, wajib untuk audit trail');
            $table->string('status')->comment('Status lifecycle jurnal, contoh: draft, posted, reversed');
            $table->unsignedBigInteger('created_by')->comment('Foreign key ke users.id, audit siapa yang membuat jurnal');
            $table->timestamp('posted_at')->nullable()->comment('Waktu jurnal resmi diposting, digunakan untuk audit & period locking');
            $table->timestamps();

            $table->index('company_id');
            $table->index('period_id');
            $table->unique(['company_id', 'journal_number'], 'uniq_company_journal_number');
            $table->foreign('company_id')->references('id')->on('companies')->onDelete('restrict');
            $table->foreign('period_id')->references('id')->on('accounting_periods')->onDelete('restrict');
            $table->foreign('created_by')->references('id')->on('users')->onDelete('restrict');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('journals');
    }
};
