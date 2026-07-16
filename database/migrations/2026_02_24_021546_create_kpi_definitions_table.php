<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('kpi_definitions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->nullable()->constrained('organizations')->onDelete('cascade');
            // NULL = system standard (visible to all), filled = custom for that org only
            $table->string('name');
            $table->enum('category', ['financial', 'non_financial']);
            $table->enum('unit', ['currency', 'percentage', 'number', 'ratio']);
            $table->enum('source', ['manual', 'auto_fs'])->default('manual');
            $table->string('fs_mapping')->nullable();
            // e.g. "revenue", "gross_profit", "ratio_current_ratio"
            $table->boolean('higher_is_better')->default(true);
            // false = lower is better (e.g. DPO, Churn Rate, Debt/Equity)
            $table->text('description')->nullable();
            $table->boolean('is_active')->default(true);
            $table->integer('sort_order')->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('kpi_definitions');
    }
};