<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('surveys', function (Blueprint $table) {
            $table->string('default_respondent_name')->nullable()->after('prepared_by');
            $table->string('default_respondent_title')->nullable()->after('default_respondent_name');
            $table->string('default_respondent_company')->nullable()->after('default_respondent_title');
            $table->boolean('show_respondent_age')->default(false)->after('default_respondent_company');
            $table->boolean('show_respondent_gender')->default(false)->after('show_respondent_age');
        });
    }

    public function down(): void
    {
        Schema::table('surveys', function (Blueprint $table) {
            $table->dropColumn([
                'default_respondent_name',
                'default_respondent_title',
                'default_respondent_company',
                'show_respondent_age',
                'show_respondent_gender',
            ]);
        });
    }
};
