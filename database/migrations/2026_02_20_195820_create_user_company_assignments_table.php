<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('user_company_assignments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')
                  ->constrained('users')
                  ->onDelete('cascade');
            $table->foreignId('portfolio_company_id')
                  ->constrained('portfolio_companies')
                  ->onDelete('cascade');
            $table->timestamps();

            // Prevent duplicate assignments
            $table->enum('role', ['manager', 'analyst', 'viewer'])->default('viewer');
            $table->unique(['user_id', 'portfolio_company_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('user_company_assignments');
    }
};