<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * This migration ensures the model_studio_workbooks.sheets_data column
 * can store a Univer snapshot (JSON object) instead of the old Handsontable
 * format (JSON array). Since it's already a JSON column, no schema change
 * is needed — this migration just documents the format change.
 *
 * The Editor.vue auto-detects the old array format on load and converts it.
 * After the first save, the workbook is stored in the new Univer format.
 */
return new class extends Migration
{
    public function up(): void
    {
        // The column is already JSON — no structural change needed.
        // We simply ensure the column exists and is nullable so that
        // brand-new workbooks (null sheets_data) are handled gracefully.

        Schema::table('model_studio_workbooks', function (Blueprint $table) {
            // Make sheets_data nullable if it isn't already
            $table->json('sheets_data')->nullable()->change();
            // Make charts_data nullable if it isn't already  
            $table->json('charts_data')->nullable()->change();
        });
    }

    public function down(): void
    {
        // Nothing to reverse — the column stays JSON either way.
    }
};