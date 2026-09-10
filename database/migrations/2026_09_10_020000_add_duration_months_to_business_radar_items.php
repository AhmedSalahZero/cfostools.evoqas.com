<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    // 999 is the internal sentinel for "more than 24 months"
    const OVER_24_SENTINEL = 999;

    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('business_radar_items', function (Blueprint $table) {
            $table->unsignedSmallInteger('duration_months')->default(1)->after('effort_score');
        });

        // Best-effort translation of the old 1-5 abstract effort score
        // into a rough duration, so existing data isn't lost.
        $map = [
            1 => 1,   // Quick / easy      -> 1 month
            2 => 3,   // Some effort       -> 3 months
            3 => 6,   // Moderate          -> 6 months
            4 => 12,  // Hard              -> 12 months
            5 => 24,  // Major undertaking -> 24 months
        ];

        foreach ($map as $oldScore => $months) {
            DB::table('business_radar_items')
                ->where('effort_score', $oldScore)
                ->update(['duration_months' => $months]);
        }

        Schema::table('business_radar_items', function (Blueprint $table) {
            $table->dropColumn('effort_score');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('business_radar_items', function (Blueprint $table) {
            $table->unsignedTinyInteger('effort_score')->default(3)->after('duration_months');
        });

        $map = [1 => 1, 2 => 2, 3 => 3, 4 => 3, 5 => 3, 6 => 3, 7 => 4, 8 => 4, 9 => 4, 10 => 4, 11 => 4, 12 => 4, 18 => 5, 24 => 5];
        foreach ($map as $months => $oldScore) {
            DB::table('business_radar_items')
                ->where('duration_months', $months)
                ->update(['effort_score' => $oldScore]);
        }
        DB::table('business_radar_items')
            ->where('duration_months', self::OVER_24_SENTINEL)
            ->update(['effort_score' => 5]);

        Schema::table('business_radar_items', function (Blueprint $table) {
            $table->dropColumn('duration_months');
        });
    }
};
