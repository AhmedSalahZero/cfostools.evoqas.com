<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('company_kpi_configs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('portfolio_company_id')
                  ->constrained('portfolio_companies')
                  ->cascadeOnDelete();
            $table->foreignId('kpi_definition_id')
                  ->constrained('kpi_definitions')
                  ->cascadeOnDelete();
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            // Fixed: give the unique index a short name
            $table->unique(
                ['portfolio_company_id', 'kpi_definition_id'],
                'comp_kpi_unique'   // ← short & safe name
            );
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('company_kpi_configs');
    }
};