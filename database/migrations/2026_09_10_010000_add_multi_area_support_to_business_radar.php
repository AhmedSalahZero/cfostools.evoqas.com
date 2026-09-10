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
        // ── Pivot: an item can now link to several business areas,
        //    each carrying its own impact weight (1-5). Effort stays
        //    a single score on the item itself.
        Schema::create('business_radar_item_areas', function (Blueprint $table) {
            $table->id();
            $table->foreignId('business_radar_item_id')
                  ->constrained('business_radar_items')
                  ->cascadeOnDelete();
            $table->foreignId('business_radar_area_id')
                  ->constrained('business_radar_areas')
                  ->cascadeOnDelete();
            $table->unsignedTinyInteger('impact_score')->default(3); // 1-5
            $table->timestamps();

            $table->unique(['business_radar_item_id', 'business_radar_area_id'], 'br_item_area_unique');
        });

        // ── Migrate existing single-area, single-impact data across ──
        $items = DB::table('business_radar_items')
            ->whereNotNull('business_radar_area_id')
            ->get(['id', 'business_radar_area_id', 'impact_score']);

        $now = now();
        foreach ($items as $item) {
            DB::table('business_radar_item_areas')->insert([
                'business_radar_item_id' => $item->id,
                'business_radar_area_id' => $item->business_radar_area_id,
                'impact_score'           => $item->impact_score ?? 3,
                'created_at'             => $now,
                'updated_at'             => $now,
            ]);
        }

        // ── Drop the now-redundant columns from business_radar_items ──
        Schema::table('business_radar_items', function (Blueprint $table) {
            $table->dropForeign(['business_radar_area_id']);
            $table->dropColumn(['business_radar_area_id', 'impact_score']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('business_radar_items', function (Blueprint $table) {
            $table->foreignId('business_radar_area_id')
                  ->nullable()
                  ->after('type')
                  ->constrained('business_radar_areas')
                  ->nullOnDelete();
            $table->unsignedTinyInteger('impact_score')->default(3)->after('description');
        });

        // Restore the item's area/impact from its first linked area, if any
        $links = DB::table('business_radar_item_areas')->orderBy('id')->get();
        $seen = [];
        foreach ($links as $link) {
            if (isset($seen[$link->business_radar_item_id])) {
                continue;
            }
            $seen[$link->business_radar_item_id] = true;
            DB::table('business_radar_items')
                ->where('id', $link->business_radar_item_id)
                ->update([
                    'business_radar_area_id' => $link->business_radar_area_id,
                    'impact_score'           => $link->impact_score,
                ]);
        }

        Schema::dropIfExists('business_radar_item_areas');
    }
};
