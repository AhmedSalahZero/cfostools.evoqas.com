<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('fs_settlement_schedules', function (Blueprint $table) {
            $table->id();
            $table->foreignId('fs_line_item_id')
                  ->constrained('fs_line_items')
                  ->cascadeOnDelete();
            // YYYY-MM  e.g. "2026-02"
            $table->string('month', 7);
            $table->decimal('amount', 20, 2)->default(0);
            $table->string('notes', 500)->nullable();
            $table->timestamps();

            $table->unique(['fs_line_item_id', 'month']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('fs_settlement_schedules');
    }
};