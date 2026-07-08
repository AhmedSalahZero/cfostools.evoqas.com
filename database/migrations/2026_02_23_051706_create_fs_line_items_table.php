<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('fs_line_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('fs_section_id')->constrained('fs_sections')->onDelete('cascade');

            // The user-typed label for this sub-row
            // e.g. "Wipes Revenue", "Body Care Revenue", "Office Rent"
            $table->string('label', 300);

            // The monetary value — stored as decimal with high precision
            // Negative values allowed (e.g. finance expenses entered as negative)
            $table->decimal('amount', 20, 4)->default(0);

            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamps();

            $table->index('fs_section_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('fs_line_items');
    }
};