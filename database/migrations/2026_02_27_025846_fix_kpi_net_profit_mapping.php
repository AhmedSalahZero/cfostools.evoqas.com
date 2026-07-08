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
    
   
    
    DB::table('kpi_definitions')
        ->where('name', 'like', '%Net Income%')
        ->orWhere('name', 'Net Income')
        ->update(['fs_mapping' => 'sales_revenue']);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        //
    }
};
