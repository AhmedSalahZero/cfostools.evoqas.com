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
    Schema::create('profitability_dashboard_notes', function (Blueprint $table) {
        $table->id();
        $table->foreignId('portfolio_company_id')->constrained('portfolio_companies')->cascadeOnDelete();
        $table->date('date_from');
        $table->date('date_to');
        $table->longText('note');
        $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
        $table->timestamps();
    });
}

public function down(): void
{
    Schema::dropIfExists('profitability_dashboard_notes');
}
};
