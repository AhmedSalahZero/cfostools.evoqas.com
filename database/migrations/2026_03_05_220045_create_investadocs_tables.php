<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // ── Document Templates (system-level, seeded once) ────────────────────
        Schema::create('doc_templates', function (Blueprint $table) {
            $table->id();
            $table->string('slug')->unique();           // 'nda', 'loi', 'term_sheet' …
            $table->string('name');                     // "Non-Disclosure Agreement"
            $table->string('short_name');               // "NDA"
            $table->string('category');                 // 'pre_loi' | 'due_diligence' | 'closing'
            $table->string('icon')->nullable();
            $table->text('description')->nullable();
            $table->json('variables');                  // [{key, label, type, placeholder, required}]
            $table->integer('sort_order')->default(0);
            $table->timestamps();
        });

        // ── Generated Documents ───────────────────────────────────────────────
        // Primary owner = organization (the PE firm doing the deal)
        // portfolio_company_id = optional, linked once prospect becomes a company in the system
        Schema::create('investadocs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->foreignId('doc_template_id')->constrained('doc_templates');
            $table->foreignId('created_by')->constrained('users');

            // Optional link to a portfolio company (for post-investment docs)
            $table->foreignId('portfolio_company_id')
                  ->nullable()
                  ->constrained()
                  ->nullOnDelete();

            $table->string('title');
            $table->string('target_company_name')->nullable(); // free-text for prospects not yet in system
            $table->string('status')->default('draft');        // draft | sent | signed | archived
            $table->json('variables_data');
            $table->string('file_path')->nullable();
            $table->timestamp('sent_at')->nullable();
            $table->timestamp('signed_at')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('investadocs');
        Schema::dropIfExists('doc_templates');
    }
};