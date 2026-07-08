<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('portfolio_companies', function (Blueprint $table) {
            // Add after 'organization_id' column
            $table->enum('type', ['investment', 'prospect'])
                  ->default('investment')
                  ->after('organization_id');
        });
    }

    public function down(): void
    {
        Schema::table('portfolio_companies', function (Blueprint $table) {
            $table->dropColumn('type');
        });
    }
};