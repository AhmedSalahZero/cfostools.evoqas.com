<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // ── 1. surveys ─────────────────────────────────────────────────────
        Schema::create('surveys', function (Blueprint $table) {
            $table->id();
            $table->foreignId('portfolio_company_id')->constrained('portfolio_companies')->cascadeOnDelete();
            $table->foreignId('organization_id')->constrained('organizations')->cascadeOnDelete();
            $table->foreignId('created_by')->constrained('users');
            $table->string('title');
            $table->text('introduction')->nullable();
            $table->string('prepared_by')->nullable();
            $table->string('link_token', 64)->unique()->nullable(); // null = draft (no link yet)
            $table->enum('status', ['draft', 'active', 'closed'])->default('draft');
            $table->boolean('is_template')->default(false);
            $table->integer('response_count')->default(0); // cached count
            $table->timestamps();
        });

        // ── 2. survey_questions ────────────────────────────────────────────
        Schema::create('survey_questions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('survey_id')->constrained('surveys')->cascadeOnDelete();
            $table->string('question_text');
            $table->enum('question_type', ['mcq', 'yes_no', 'rating', 'short_text', 'number', 'dropdown']);
            $table->integer('sort_order')->default(0);
            $table->boolean('is_required')->default(false);
            $table->string('placeholder')->nullable(); // for short_text / number
            $table->integer('rating_max')->default(5); // for rating type
            $table->timestamps();
        });

        // ── 3. survey_question_options ─────────────────────────────────────
        Schema::create('survey_question_options', function (Blueprint $table) {
            $table->id();
            $table->foreignId('survey_question_id')->constrained('survey_questions')->cascadeOnDelete();
            $table->string('option_text');
            $table->integer('sort_order')->default(0);
            $table->timestamps();
        });

        // ── 4. survey_responses ────────────────────────────────────────────
        Schema::create('survey_responses', function (Blueprint $table) {
            $table->id();
            $table->foreignId('survey_id')->constrained('surveys')->cascadeOnDelete();
            $table->string('respondent_name')->nullable();
            $table->string('respondent_title')->nullable();
            $table->string('respondent_company')->nullable();
            $table->enum('respondent_gender', ['male', 'female', 'prefer_not_to_say'])->nullable();
            $table->unsignedTinyInteger('respondent_age')->nullable();
            $table->string('ip_address', 45)->nullable();
            $table->timestamps();
        });

        // ── 5. survey_answers ──────────────────────────────────────────────
        Schema::create('survey_answers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('survey_response_id')->constrained('survey_responses')->cascadeOnDelete();
            $table->foreignId('survey_question_id')->constrained('survey_questions')->cascadeOnDelete();
            $table->text('answer_text')->nullable();       // short_text, number, yes_no, rating
            $table->foreignId('answer_option_id')->nullable()->constrained('survey_question_options')->nullOnDelete(); // mcq/dropdown
            $table->timestamps();
        });

        // ── 6. question_bank_sections ──────────────────────────────────────
        Schema::create('question_bank_sections', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained('organizations')->cascadeOnDelete();
            $table->string('name'); // Finance, Legal, Marketing, etc.
            $table->string('color', 20)->default('blue'); // for color badge
            $table->integer('sort_order')->default(0);
            $table->timestamps();
        });

        // ── 7. question_bank_items ─────────────────────────────────────────
        Schema::create('question_bank_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained('organizations')->cascadeOnDelete();
            $table->foreignId('question_bank_section_id')->nullable()->constrained('question_bank_sections')->nullOnDelete();
            $table->string('question_text');
            $table->enum('question_type', ['mcq', 'yes_no', 'rating', 'short_text', 'number', 'dropdown']);
            $table->boolean('is_required')->default(false);
            $table->integer('rating_max')->default(5);
            $table->string('placeholder')->nullable();
            $table->unsignedInteger('usage_count')->default(0);
            $table->timestamps();
        });

        // ── 8. question_bank_item_options ──────────────────────────────────
        Schema::create('question_bank_item_options', function (Blueprint $table) {
            $table->id();
            $table->foreignId('question_bank_item_id')->constrained('question_bank_items')->cascadeOnDelete();
            $table->string('option_text');
            $table->integer('sort_order')->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('question_bank_item_options');
        Schema::dropIfExists('question_bank_items');
        Schema::dropIfExists('question_bank_sections');
        Schema::dropIfExists('survey_answers');
        Schema::dropIfExists('survey_responses');
        Schema::dropIfExists('survey_question_options');
        Schema::dropIfExists('survey_questions');
        Schema::dropIfExists('surveys');
    }
};