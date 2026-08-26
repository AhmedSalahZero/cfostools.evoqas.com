<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('survey_sections', function (Blueprint $table) {
            $table->id();
            $table->foreignId('survey_id')->constrained('surveys')->cascadeOnDelete();
            $table->string('title');
            $table->integer('sort_order')->default(0);
            $table->timestamps();
        });

        Schema::table('survey_questions', function (Blueprint $table) {
            $table->foreignId('survey_section_id')
                ->nullable()
                ->after('survey_id')
                ->constrained('survey_sections')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('survey_questions', function (Blueprint $table) {
            $table->dropConstrainedForeignId('survey_section_id');
        });

        Schema::dropIfExists('survey_sections');
    }
};
