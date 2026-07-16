<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('kpi_trackings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('portfolio_company_id')->constrained()->onDelete('cascade');
            $table->string('kpi_name', 100);
            $table->date('period');                         // usually last day of month
            $table->decimal('target_amount', 15, 2);
            $table->decimal('actual_amount', 15, 2);
            $table->decimal('variance_percent', 6, 2)->virtualAs('(actual_amount - target_amount) / target_amount * 100');
            $table->text('comments')->nullable();
            $table->text('action_items')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('kpi_trackings');
    }
};