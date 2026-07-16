<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('study_opening_balances', function (Blueprint $table) {
            $table->id();

            $table->foreignId('financial_study_id')
                  ->constrained('financial_studies')
                  ->onDelete('cascade');

            // ── Meta ──────────────────────────────────────────────────────
            $table->date('as_of_date')->nullable();
            $table->string('notes', 500)->nullable();
            $table->boolean('is_balanced')->default(false);

            // ── Fixed Assets ──────────────────────────────────────────────
            // JSON array of objects:
            // { label, gross_amount, accum_dep, monthly_dep, dep_months_remaining, dep_mfg_pct }
            $table->json('fixed_assets')->nullable();

            // Computed totals for quick access by the Results Engine
            $table->decimal('total_gross_fa',   18, 2)->default(0);
            $table->decimal('total_accum_dep',  18, 2)->default(0);
            $table->decimal('total_net_fa',     18, 2)->default(0);

            // ── Inventories ───────────────────────────────────────────────
            // JSON array: { label, type (manufacturing_fg|manufacturing_rm|trading), amount }
            $table->json('inventory')->nullable();
            $table->decimal('total_inventory',  18, 2)->default(0);

            // ── Current Assets (excl. inventory) ─────────────────────────
            // JSON array: { label, amount, schedule[] }
            $table->json('current_assets')->nullable();
            $table->decimal('total_current_assets', 18, 2)->default(0);

            // ── Other Non-Current Assets ──────────────────────────────────
            $table->json('other_non_current')->nullable();
            $table->decimal('total_other_non_current', 18, 2)->default(0);

            // ── Long-Term Liabilities ─────────────────────────────────────
            // JSON array: { label, amount, schedule[] }
            $table->json('long_term_liabilities')->nullable();
            $table->decimal('total_long_term_liabilities', 18, 2)->default(0);

            // ── Current Liabilities ───────────────────────────────────────
            $table->json('current_liabilities')->nullable();
            $table->decimal('total_current_liabilities', 18, 2)->default(0);

            // ── Equity ────────────────────────────────────────────────────
            $table->json('equity')->nullable();
            $table->decimal('total_equity', 18, 2)->default(0);

            // ── Grand totals (for quick balance validation) ───────────────
            $table->decimal('total_assets',      18, 2)->default(0);
            $table->decimal('total_liabilities', 18, 2)->default(0);

            $table->timestamps();

            // Only one opening balance per study
            $table->unique('financial_study_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('study_opening_balances');
    }
};