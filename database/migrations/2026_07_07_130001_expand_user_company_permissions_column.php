<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement('ALTER TABLE user_company_permissions MODIFY permission VARCHAR(64) NOT NULL');
    }

    public function down(): void
    {
        DB::statement("ALTER TABLE user_company_permissions MODIFY permission ENUM(
            'view_company',
            'view_dashboard',
            'sales_analysis',
            'expense_analysis',
            'kpi_tracking',
            'financial_statements',
            'documents',
            'financial_planning'
        ) NOT NULL");
    }
};
