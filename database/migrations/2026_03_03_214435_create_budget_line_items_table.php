<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('budget_line_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('budget_group_id')->constrained()->cascadeOnDelete();

            // User-defined line item label (e.g. "iPhone Sales", "Service Fee")
            $table->string('label', 220);

            // Monthly budget amounts: {1: 50000, 2: 48000, ..., 12: 55000}
            // Keyed by month number (1–12)
            $table->json('monthly_amounts')->nullable();

            $table->unsignedTinyInteger('sort_order')->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('budget_line_items');
    }
};