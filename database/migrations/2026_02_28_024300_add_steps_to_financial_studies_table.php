<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('financial_studies', function (Blueprint $table) {
            // Add cogs_data if it doesn't exist yet
            if (!Schema::hasColumn('financial_studies', 'cogs_data')) {
                $table->longText('cogs_data')->nullable()->after('projections');
            }
            // Add manpower_data
            if (!Schema::hasColumn('financial_studies', 'manpower_data')) {
                $table->longText('manpower_data')->nullable()->after('cogs_data');
            }
            // Add expenses_data
            if (!Schema::hasColumn('financial_studies', 'expenses_data')) {
                $table->longText('expenses_data')->nullable()->after('manpower_data');
            }
        });
    }

    public function down(): void
    {
        Schema::table('financial_studies', function (Blueprint $table) {
            $table->dropColumnIfExists('manpower_data');
            $table->dropColumnIfExists('expenses_data');
        });
    }
};