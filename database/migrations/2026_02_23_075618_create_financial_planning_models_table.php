<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
{
    Schema::create('financial_planning_models', function (Blueprint $table) {
        $table->id();
        $table->foreignId('portfolio_company_id')->constrained()->cascadeOnDelete();
        
        // ← This is the corrected line
        $table->foreignId('uploaded_by')
              ->nullable()                          // ← Add this
              ->constrained('users')
              ->onDelete('set null');
        
        $table->string('name');
        $table->string('original_filename');
        $table->string('file_path');
        $table->string('version')->nullable();
        $table->text('notes')->nullable();
        $table->timestamps();
    });
}

    public function down(): void
    {
        Schema::dropIfExists('financial_planning_models');
    }
};