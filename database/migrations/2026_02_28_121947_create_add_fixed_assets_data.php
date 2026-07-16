<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('financial_studies', function (Blueprint $table) {
            $table->longText('fixed_assets_data')->nullable()->after('expenses_data');
        });
    }

    public function down(): void
    {
        Schema::table('financial_studies', function (Blueprint $table) {
            $table->dropColumn('fixed_assets_data');
        });
    }
};