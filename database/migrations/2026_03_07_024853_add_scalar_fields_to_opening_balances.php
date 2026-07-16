<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('study_opening_balances', function (Blueprint $table) {
            // Only add columns if they don't already exist (safe to re-run)
            if (!Schema::hasColumn('study_opening_balances', 'cash_bank')) {
                $table->decimal('cash_bank', 18, 2)->default(0)->after('is_balanced');
            }
            if (!Schema::hasColumn('study_opening_balances', 'paid_up_capital')) {
                $table->decimal('paid_up_capital', 18, 2)->default(0)->after('cash_bank');
            }
            if (!Schema::hasColumn('study_opening_balances', 'legal_reserve')) {
                $table->decimal('legal_reserve', 18, 2)->default(0)->after('paid_up_capital');
            }
            if (!Schema::hasColumn('study_opening_balances', 'retained_earnings')) {
                $table->decimal('retained_earnings', 18, 2)->default(0)->after('legal_reserve');
            }
        });
    }

    public function down(): void
    {
        Schema::table('study_opening_balances', function (Blueprint $table) {
            $table->dropColumn(['cash_bank', 'paid_up_capital', 'legal_reserve', 'retained_earnings']);
        });
    }
};