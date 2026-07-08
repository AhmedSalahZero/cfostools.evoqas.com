<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // Step 1: Add a temp column
        Schema::table('documents', function (Blueprint $table) {
            $table->string('category_new', 50)->nullable()->after('category');
        });

        // Step 2: Migrate old values to new ones
        DB::statement("
            UPDATE documents SET category_new = CASE
                WHEN category = 'due_diligence' THEN 'due_diligence'
                WHEN category = 'spa'           THEN 'contracts_legal'
                WHEN category = 'financials'    THEN 'financial_documents'
                ELSE 'other'
            END
        ");

        // Step 3: Drop old column
        Schema::table('documents', function (Blueprint $table) {
            $table->dropColumn('category');
        });

        // Step 4: Add new column with correct enum
        Schema::table('documents', function (Blueprint $table) {
            $table->enum('category', [
                'due_diligence',
                'contracts_legal',
                'financial_documents',
                'corporate_documents',
                'operational',
                'other',
            ])->default('other')->after('category_new');
        });

        // Step 5: Copy migrated values
        DB::statement("UPDATE documents SET category = category_new WHERE category_new IS NOT NULL");

        // Step 6: Drop temp column
        Schema::table('documents', function (Blueprint $table) {
            $table->dropColumn('category_new');
        });
    }

    public function down(): void
    {
        Schema::table('documents', function (Blueprint $table) {
            $table->dropColumn('category');
        });
        Schema::table('documents', function (Blueprint $table) {
            $table->enum('category', ['due_diligence', 'spa', 'financials', 'other'])
                  ->default('other');
        });
    }
};