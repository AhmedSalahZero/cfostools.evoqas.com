<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('portfolio_company_notes', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('portfolio_company_id');
            $table->text('note');
            $table->string('action_items')->nullable();   // short action text
            $table->enum('priority', ['high', 'medium', 'low'])->default('medium');
            $table->unsignedBigInteger('created_by');
            $table->timestamps();

            $table->foreign('portfolio_company_id')->references('id')->on('portfolio_companies')->onDelete('cascade');
            $table->foreign('created_by')->references('id')->on('users')->onDelete('cascade');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('portfolio_company_notes');
    }
};