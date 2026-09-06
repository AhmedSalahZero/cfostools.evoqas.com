<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Partially completed public survey responses. A respondent can stop midway,
     * keep the resume link, and come back to finish. Kept separate from
     * survey_responses so drafts never leak into results or response counts.
     */
    public function up(): void
    {
        Schema::create('survey_drafts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('survey_id')->constrained('surveys')->cascadeOnDelete();
            $table->string('token', 64)->unique();     // goes in the resume link
            $table->json('respondent')->nullable();    // name / title / age / gender
            $table->json('answers')->nullable();       // { questionId: answer }
            $table->string('ip_address', 45)->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('survey_drafts');
    }
};
