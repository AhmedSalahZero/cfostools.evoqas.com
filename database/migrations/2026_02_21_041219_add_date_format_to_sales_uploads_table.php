<?php

// Run: php artisan make:migration add_date_format_to_sales_uploads_table
// Then paste this content into the new migration file

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('sales_uploads', function (Blueprint $table) {
            $table->string('date_format', 20)->default('DD/MM/YYYY')->after('period_to');
        });
    }

    public function down(): void
    {
        Schema::table('sales_uploads', function (Blueprint $table) {
            $table->dropColumn('date_format');
        });
    }
};