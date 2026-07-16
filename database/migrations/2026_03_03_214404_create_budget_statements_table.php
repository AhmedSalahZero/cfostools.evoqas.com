<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('budget_statements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('portfolio_company_id')->constrained()->cascadeOnDelete();
            $table->string('name');                          // e.g. "2026 Annual Budget"
            $table->unsignedSmallInteger('year');            // e.g. 2026
            $table->string('currency', 10)->default('USD');
            $table->enum('status', ['draft', 'final'])->default('draft');
            $table->text('notes')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('budget_statements');
    }
};