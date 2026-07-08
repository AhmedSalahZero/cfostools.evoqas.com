<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('budget_groups', function (Blueprint $table) {
            $table->id();
            $table->foreignId('budget_section_id')->constrained()->cascadeOnDelete();

            // User-defined group name (e.g. "Product Line A", "Egypt Region")
            $table->string('name', 180);

            $table->unsignedTinyInteger('sort_order')->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('budget_groups');
    }
};