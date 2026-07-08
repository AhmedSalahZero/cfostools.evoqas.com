<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
   public function up(): void
{
    Schema::create('profitability_pl_mappings', function (Blueprint $table) {
        $table->id();
        $table->foreignId('portfolio_company_id')->constrained('portfolio_companies')->cascadeOnDelete();
        $table->string('expense_category');
        $table->enum('pl_line', ['cogs', 'opex', 'da', 'interest', 'tax', 'other'])->default('opex');
        $table->timestamps();
        $table->unique(['portfolio_company_id', 'expense_category']);
    });
}

public function down(): void
{
    Schema::dropIfExists('profitability_pl_mappings');
}
};
