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
      ->where('name', 'Net Income')           // exact match - safest
      ->orWhere('name', 'like', '%Net Income%') // catches variations like "Net Income (After Tax)"
         ->update(['name' => 'Net Profit']);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('mapping', function (Blueprint $table) {
            //
        });
    }
};
