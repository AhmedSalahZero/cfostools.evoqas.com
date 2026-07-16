<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // ── 1. Sales Directors assigned to a budget ───────────────────────────────
        // Stores which users are Sales Directors for a given budget statement.
        // One budget can have many directors; one director can appear in many budgets.
        Schema::create('budget_sales_directors', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('budget_statement_id');
            $table->unsignedBigInteger('user_id');                // the Sales Director
            $table->string('name');                               // display name (denormalized for speed)
            $table->string('title')->nullable();                  // e.g. "Regional Sales Director"
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();

            $table->foreign('budget_statement_id')
                  ->references('id')->on('budget_statements')
                  ->onDelete('cascade');

            $table->unique(['budget_statement_id', 'user_id']);
        });

        // ── 2. Line-item → Sales Director assignments ─────────────────────────────
        // A Sales Revenue line item can be assigned to ONE director.
        // Only line items inside the sales_revenue section are relevant.
        Schema::create('budget_line_item_assignments', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('budget_line_item_id');
            $table->unsignedBigInteger('budget_sales_director_id');
            $table->timestamps();

            $table->foreign('budget_line_item_id')
                  ->references('id')->on('budget_line_items')
                  ->onDelete('cascade');

            $table->foreign('budget_sales_director_id')
                  ->references('id')->on('budget_sales_directors')
                  ->onDelete('cascade');

            $table->unique('budget_line_item_id');  // one director per line item
        });

        // ── 3. Director Review Room entries ──────────────────────────────────────
        // Stores the director's monthly review inputs:
        //   - variance_comment : why actual differed from budget
        //   - action_taken     : what corrective action was done / planned
        //   - pipeline         : confirmed deals not yet invoiced
        //   - prospects        : unconfirmed opportunities being tracked
        Schema::create('budget_director_reviews', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('budget_sales_director_id');
            $table->unsignedTinyInteger('month');                 // 1–12
            $table->text('variance_comment')->nullable();
            $table->text('action_taken')->nullable();
            $table->decimal('pipeline_amount', 18, 2)->nullable(); // confirmed pipeline value
            $table->text('pipeline_notes')->nullable();
            $table->decimal('prospects_amount', 18, 2)->nullable();// unconfirmed prospects value
            $table->text('prospects_notes')->nullable();
            $table->string('priority')->default('medium');         // low / medium / high
            $table->unsignedBigInteger('saved_by')->nullable();
            $table->timestamps();

            $table->foreign('budget_sales_director_id')
                  ->references('id')->on('budget_sales_directors')
                  ->onDelete('cascade');

            $table->unique(['budget_sales_director_id', 'month']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('budget_director_reviews');
        Schema::dropIfExists('budget_line_item_assignments');
        Schema::dropIfExists('budget_sales_directors');
    }
};