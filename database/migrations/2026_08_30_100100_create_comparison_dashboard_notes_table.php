<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('comparison_dashboard_notes', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('comparison_dashboard_id');
            // Which section this note belongs to, e.g. 'zoom_out',
            // 'zoom_in_0_1' (period index 0 vs 1), 'branch_analysis', etc.
            $table->string('section_key');
            $table->longText('note');
            $table->unsignedBigInteger('updated_by')->nullable();
            $table->timestamps();

            $table->foreign('comparison_dashboard_id')
                  ->references('id')->on('comparison_dashboards')
                  ->onDelete('cascade');
            $table->foreign('updated_by')
                  ->references('id')->on('users')
                  ->onDelete('set null');
            $table->unique(['comparison_dashboard_id', 'section_key'], 'cd_notes_dashboard_section_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('comparison_dashboard_notes');
    }
};