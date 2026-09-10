<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // ── Business Areas ──────────────────────────────────────────────
        // organization_id = null  → built-in, available to every organization
        // organization_id = X     → custom area added by that organization
        Schema::create('business_radar_areas', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')
                  ->nullable()
                  ->constrained('organizations')
                  ->cascadeOnDelete();
            $table->string('name');
            $table->string('slug');
            $table->unsignedInteger('sort_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->unique(['organization_id', 'slug']);
        });

        // ── Boards ───────────────────────────────────────────────────────
        // A company can have several named Business Radar boards
        // (e.g. "Q3 2026 Diagnostic"), each a living, editable list.
        Schema::create('business_radar_boards', function (Blueprint $table) {
            $table->id();
            $table->foreignId('portfolio_company_id')
                  ->constrained('portfolio_companies')
                  ->cascadeOnDelete();
            $table->string('name');
            $table->text('description')->nullable();
            $table->foreignId('created_by')
                  ->nullable()
                  ->constrained('users')
                  ->nullOnDelete();
            $table->timestamps();
        });

        // ── Items (Challenges & Potentials) ─────────────────────────────
        // One table, distinguished by `type`, so both lists share the
        // same scoring, status and area-tagging logic.
        Schema::create('business_radar_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('business_radar_board_id')
                  ->constrained('business_radar_boards')
                  ->cascadeOnDelete();
            $table->enum('type', ['challenge', 'potential']);
            $table->foreignId('business_radar_area_id')
                  ->nullable()
                  ->constrained('business_radar_areas')
                  ->nullOnDelete();
            $table->string('title');
            $table->text('description')->nullable();
            $table->unsignedTinyInteger('impact_score')->default(3);  // 1-5
            $table->unsignedTinyInteger('effort_score')->default(3);  // 1-5
            $table->string('status')->default('open');
            // challenge statuses: open, in_progress, resolved
            // potential statuses: open, in_progress, captured
            $table->longText('notes')->nullable();
            $table->foreignId('created_by')
                  ->nullable()
                  ->constrained('users')
                  ->nullOnDelete();
            $table->timestamps();

            $table->index(['business_radar_board_id', 'type']);
        });

        // ── Links ────────────────────────────────────────────────────────
        // Optional many-to-many connections between a challenge and a
        // potential, each with its own strength. Purely contextual —
        // items are always valid standing alone.
        Schema::create('business_radar_links', function (Blueprint $table) {
            $table->id();
            $table->foreignId('challenge_item_id')
                  ->constrained('business_radar_items')
                  ->cascadeOnDelete();
            $table->foreignId('potential_item_id')
                  ->constrained('business_radar_items')
                  ->cascadeOnDelete();
            $table->unsignedTinyInteger('strength')->default(2); // 1=weak 2=medium 3=strong
            $table->string('note')->nullable();
            $table->timestamps();

            $table->unique(['challenge_item_id', 'potential_item_id'], 'br_links_unique_pair');
        });

        // ── Seed built-in business areas ───────────────────────────────
        $defaults = [
            'Sales',
            'Profit',
            'Cash Flow',
            'Cost Saving',
            'Sales Activities',
            'Marketing Activities',
            'HR',
            'Operations',
            'Customer Service',
        ];

        $now = now();
        foreach ($defaults as $i => $name) {
            DB::table('business_radar_areas')->insert([
                'organization_id' => null,
                'name'            => $name,
                'slug'            => \Illuminate\Support\Str::slug($name),
                'sort_order'      => $i,
                'is_active'       => true,
                'created_at'      => $now,
                'updated_at'      => $now,
            ]);
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('business_radar_links');
        Schema::dropIfExists('business_radar_items');
        Schema::dropIfExists('business_radar_boards');
        Schema::dropIfExists('business_radar_areas');
    }
};
