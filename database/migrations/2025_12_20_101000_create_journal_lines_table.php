<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('journal_lines', function (Blueprint $table) {
            $table->bigIncrements('id')->comment('Primary key detail jurnal');
            $table->unsignedBigInteger('journal_id')->comment('Foreign key ke journals.id, menghubungkan detail ke header jurnal');
            $table->unsignedBigInteger('account_id')->comment('Foreign key ke chart_of_accounts.id, menentukan akun GL');
            $table->decimal('debit', 18, 2)->default(0)->comment('Nilai debit, default 0');
            $table->decimal('credit', 18, 2)->default(0)->comment('Nilai kredit, default 0');
            $table->unsignedBigInteger('department_id')->nullable()->comment('Foreign key ke departments.id, digunakan untuk analisis biaya per department');
            $table->unsignedBigInteger('project_id')->nullable()->comment('Foreign key ke projects.id, digunakan untuk analisis biaya per project');
            $table->text('description')->nullable()->comment('Narasi per baris jurnal');
            $table->timestamps();

            $table->index('journal_id');
            $table->index('account_id');
            $table->index('department_id');
            $table->index('project_id');
            $table->foreign('journal_id')->references('id')->on('journals')->onDelete('cascade');
            $table->foreign('account_id')->references('id')->on('chart_of_accounts')->onDelete('restrict');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('journal_lines');
    }
};
