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
    Schema::create('sales_field_mappings', function (Blueprint $table) {
        $table->id();
        $table->foreignId('portfolio_company_id')->constrained()->onDelete('cascade');
        $table->string('field_key', 100);  // e.g. 'branch', 'sales_person'
        $table->boolean('is_active')->default(true);
        $table->integer('sort_order')->default(0);
        $table->timestamps();
        $table->unique(['portfolio_company_id', 'field_key']);
    });
}

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('sales_field_mappings');
    }
};
