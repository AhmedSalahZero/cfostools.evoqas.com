<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('budget_actuals', function (Blueprint $table) {
            $table->id();
            $table->foreignId('budget_line_item_id')->constrained()->cascadeOnDelete();

            // Monthly actual amounts: {1: 48000, 2: 51000, ..., 12: 0}
            // Keyed by month number (1–12). Null = not entered yet.
            $table->json('monthly_actuals')->nullable();

            // Source of actuals: manual | fs_import
            // fs_import = pulled from the existing Financial Statements module
            $table->enum('source', ['manual', 'fs_import'])->default('manual');

            // Which financial statement was used as source (nullable — only set for fs_import)
            $table->foreignId('source_statement_id')
                  ->nullable()
                  ->constrained('financial_statements')
                  ->nullOnDelete();

            $table->foreignId('entered_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('budget_actuals');
    }
};