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
        ->where('name', 'like', '%Revenue%')
        ->orWhere('name', 'Revenue')
        ->update(['fs_mapping' => 'sales_revenue']);

    DB::table('kpi_definitions')
        ->where('name', 'like', '%EBIT%')
        ->where('name', 'not like', '%Margin%')
        ->update(['fs_mapping' => 'ebit']);

    DB::table('kpi_definitions')
        ->where('name', 'like', '%EBIT Margin%')
        ->update(['fs_mapping' => 'ebit_margin_pct']);

    DB::table('kpi_definitions')
        ->where('name', 'like', '%EBT%')
        ->where('name', 'not like', '%Margin%')
        ->update(['fs_mapping' => 'ebt']);

    DB::table('kpi_definitions')
        ->where('name', 'like', '%EBT Margin%')
        ->update(['fs_mapping' => 'ebt_margin_pct']);
    
    DB::table('kpi_definitions')
        ->where('name', 'like', '%Net Profit%')
        ->where('name', 'not like', '%Margin%')
        ->update(['fs_mapping' => 'net_profit']);

    DB::table('kpi_definitions')
        ->where('name', 'like', '%Net Profit Margin%')
        ->update(['fs_mapping' => 'net_margin_pct']);

    DB::table('kpi_definitions')
        ->where('name', 'like', '%Current Ratio%')
        ->update(['fs_mapping' => 'current_ratio']);

    DB::table('kpi_definitions')
        ->where('name', 'like', '%Quick Ratio%')
        ->update(['fs_mapping' => 'quick_ratio']);

    DB::table('kpi_definitions')
        ->where('name', 'like', '%Return on Equity%')
        ->update(['fs_mapping' => 'roe']);


    DB::table('kpi_definitions')
        ->where('name', 'like', '%Return on Assets%')
        ->update(['fs_mapping' => 'roa']);
    
    DB::table('kpi_definitions')
        ->where('name', 'like', '%Debt to Equity%')
        ->update(['fs_mapping' => 'debt_to_equity']);

    DB::table('kpi_definitions')
        ->where('name', 'like', '%Interest Coverage%')
        ->update(['fs_mapping' => 'interest_coverage']);

    DB::table('kpi_definitions')
        ->where('name', 'like', '%Days Sales Outstanding%')
        ->orWhere('name', 'like', '%Days Sales%')
        ->update(['fs_mapping' => 'dso']);

     DB::table('kpi_definitions')
        ->where('name', 'like', '%Days Inventory Outstanding%')
        ->orWhere('name', 'like', '%Days Sales%')
        ->update(['fs_mapping' => 'dio']);

    DB::table('kpi_definitions')
        ->where('name', 'like', '%Days Payable Outstanding%')
        ->orWhere('name', 'like', '%Days Sales%')
        ->update(['fs_mapping' => 'dpo']);



    // Add more rules here if you have other missing KPIs
}

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        //
    }
};
