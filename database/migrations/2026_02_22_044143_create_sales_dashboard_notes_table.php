<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sales_dashboard_notes', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('portfolio_company_id');
            $table->date('date_from');
            $table->date('date_to');
            $table->unsignedBigInteger('created_by');
            $table->text('note');
            $table->timestamps();

            $table->foreign('portfolio_company_id')
                  ->references('id')->on('portfolio_companies')
                  ->onDelete('cascade');
            $table->foreign('created_by')
                  ->references('id')->on('users')
                  ->onDelete('cascade');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sales_dashboard_notes');
    }
};