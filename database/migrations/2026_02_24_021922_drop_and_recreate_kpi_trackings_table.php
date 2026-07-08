<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::dropIfExists('kpi_trackings');

        Schema::create('kpi_trackings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained('portfolio_companies')->onDelete('cascade');
            $table->foreignId('kpi_definition_id')->constrained('kpi_definitions')->onDelete('cascade');
            $table->enum('period_type', ['monthly', 'quarterly', 'annual']);
            $table->string('period_label'); // e.g. "2025-01", "2025-Q1", "2025"
            $table->decimal('target', 20, 4)->nullable();
            $table->decimal('actual', 20, 4)->nullable();
            $table->text('notes')->nullable();
            $table->foreignId('entered_by')->nullable()->constrained('users')->onDelete('set null');
            $table->boolean('auto_synced')->default(false);
            $table->timestamps();

            $table->unique(['company_id', 'kpi_definition_id', 'period_type', 'period_label'], 'kpi_trackings_unique');
     });
    
            }

    public function down(): void
    {
        Schema::dropIfExists('kpi_trackings');
    }
};