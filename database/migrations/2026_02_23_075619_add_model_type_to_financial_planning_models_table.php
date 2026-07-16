<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('financial_planning_models', function (Blueprint $table) {
            $table->enum('model_type', ['complex', 'simple'])
                  ->default('complex')
                  ->after('name');
        });
    }

    public function down(): void
    {
        Schema::table('financial_planning_models', function (Blueprint $table) {
            $table->dropColumn('model_type');
        });
    }
};