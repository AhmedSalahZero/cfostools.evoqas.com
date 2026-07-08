<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('documents', function (Blueprint $table) {
            $table->id();
            $table->foreignId('portfolio_company_id')->constrained()->onDelete('cascade');
            $table->string('name');
            $table->string('path', 512);
            $table->string('mime_type', 100)->nullable();
            $table->enum('category', ['due_diligence', 'spa', 'financials', 'other'])->default('other');
            $table->foreignId('uploaded_by')->nullable()->constrained('users');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('documents');
    }
};