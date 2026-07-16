<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('fs_line_items', function (Blueprint $table) {
            $table->enum('cf_category', ['operating', 'investing', 'financing'])
                ->nullable()
                ->after('amount');
        });
    }

    public function down(): void
    {
        Schema::table('fs_line_items', function (Blueprint $table) {
            $table->dropColumn('cf_category');
        });
    }
};
