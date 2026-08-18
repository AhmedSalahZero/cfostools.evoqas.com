<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('data_room_sections', function (Blueprint $table) {
            $table->id();
            $table->foreignId('portfolio_company_id')->constrained('portfolio_companies')->cascadeOnDelete();
            $table->string('name');
            $table->string('icon', 32)->default('📁');
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();
        });

        Schema::create('data_room_subsections', function (Blueprint $table) {
            $table->id();
            $table->foreignId('data_room_section_id')->constrained('data_room_sections')->cascadeOnDelete();
            $table->string('name');
            $table->string('icon', 32)->default('📄');
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();
        });

        Schema::table('documents', function (Blueprint $table) {
            $table->foreignId('data_room_subsection_id')
                ->nullable()
                ->after('portfolio_company_id')
                ->constrained('data_room_subsections')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('documents', function (Blueprint $table) {
            $table->dropConstrainedForeignId('data_room_subsection_id');
        });

        Schema::dropIfExists('data_room_subsections');
        Schema::dropIfExists('data_room_sections');
    }
};
