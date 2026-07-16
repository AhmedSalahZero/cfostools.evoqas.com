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
    Schema::create('financial_studies', function (Blueprint $table) {
        $table->id();
        
        $table->foreignId('portfolio_company_id')
              ->constrained('portfolio_companies')
              ->onDelete('cascade');

        $table->string('name');                              // e.g. "Racking System BP 2025-2029"
        $table->string('study_currency')->default('EGP');

        $table->date('study_start_date');
        $table->integer('duration_years')->default(5);
        $table->date('study_end_date')->nullable();
        $table->date('operation_start_date')->nullable();

        $table->enum('business_type', ['manufacturing', 'trading', 'service'])
              ->default('manufacturing');
        $table->string('business_sector')->nullable();

        $table->decimal('corporate_tax_rate', 8, 4)->default(22.50);
        $table->decimal('required_investment_return_pct', 8, 4)->default(30.00);
        $table->decimal('perpetual_growth_rate_pct', 8, 4)->default(4.00);

        $table->json('general_assumptions')->nullable();   // We will put "Company Type" + other extra fields here later
        $table->json('projections')->nullable();           // All calculated results

        $table->longText('comments')->nullable();
        $table->timestamps();
    });
}



};
