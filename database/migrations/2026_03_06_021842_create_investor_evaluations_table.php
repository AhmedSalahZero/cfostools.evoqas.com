<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('investor_evaluations', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('organization_id');
            $table->unsignedBigInteger('portfolio_company_id');
            $table->string('company_name');
            $table->string('saved_name')->nullable();
            $table->json('scores')->nullable();
            $table->json('thresholds')->nullable();
            $table->text('notes')->nullable();
            $table->string('verdict', 30)->nullable();
            $table->timestamps();

            $table->index(['organization_id', 'portfolio_company_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('investor_evaluations');
    }
};