<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('comparison_dashboards', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('portfolio_company_id');
            $table->string('name');
            // Array of {label, from, to} — 2 to 5 user-chosen periods.
            // Always recomputed live on view, never cached, so a saved
            // dashboard reflects whatever the sales data looks like today.
            $table->json('periods');
            $table->string('share_token', 40)->unique();
            $table->boolean('is_public')->default(false);
            $table->unsignedBigInteger('created_by');
            $table->timestamps();

            $table->foreign('portfolio_company_id')
                  ->references('id')->on('portfolio_companies')
                  ->onDelete('cascade');
            $table->foreign('created_by')
                  ->references('id')->on('users')
                  ->onDelete('cascade');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('comparison_dashboards');
    }
};
