<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('user_company_permissions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')
                  ->constrained('users')
                  ->onDelete('cascade');
            $table->foreignId('portfolio_company_id')
                  ->constrained('portfolio_companies')
                  ->onDelete('cascade');
            $table->enum('permission', [
                'view_company',
                'view_dashboard',
                'sales_analysis',
                'expense_analysis',
                'kpi_tracking',
                'financial_statements',
                'documents',
                'financial_planning',
            ]);
            $table->timestamps();

            // Short index name to stay within MySQL's 64 char limit
            $table->unique(
                ['user_id', 'portfolio_company_id', 'permission'],
                'ucp_unique'
            );
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('user_company_permissions');
    }
};