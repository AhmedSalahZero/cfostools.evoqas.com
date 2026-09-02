<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('comparison_dashboards', function (Blueprint $table) {
            // 'sales' (original behavior) or 'expense'. Existing rows default
            // to 'sales' so nothing already saved changes behavior.
            $table->string('type', 20)->default('sales')->after('portfolio_company_id');
        });
    }

    public function down(): void
    {
        Schema::table('comparison_dashboards', function (Blueprint $table) {
            $table->dropColumn('type');
        });
    }
};
