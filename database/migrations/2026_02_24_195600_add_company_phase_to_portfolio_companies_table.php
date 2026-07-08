<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('portfolio_companies', function (Blueprint $table) {
            $table->enum('company_phase', [
                'pre-seed', 'seed', 'series-a', 'series-b', 'series-c',
                'growth', 'mature', 'exited', 'deadpooled'
            ])->nullable()->after('name');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('portfolio_companies', function (Blueprint $table) {
            $table->dropColumn('company_phase');
        });
    }
};
