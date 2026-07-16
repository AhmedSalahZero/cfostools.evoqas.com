<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('model_studio_workbooks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('portfolio_company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('created_by')->constrained('users')->cascadeOnDelete();
            $table->string('name');                        // workbook name
            $table->longText('sheets_data')->nullable();   // JSON: all sheets + cell data
            $table->longText('charts_data')->nullable();   // JSON: chart configs
            $table->timestamp('last_saved_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('model_studio_workbooks');
    }
};