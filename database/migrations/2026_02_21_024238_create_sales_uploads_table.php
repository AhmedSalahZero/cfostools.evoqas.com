<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
   public function up(): void
{
    Schema::create('sales_uploads', function (Blueprint $table) {
        $table->id();
        $table->foreignId('portfolio_company_id')->constrained()->onDelete('cascade');
        $table->foreignId('uploaded_by')->constrained('users')->onDelete('cascade');
        $table->string('file_path', 512);
        $table->date('period_from');
        $table->date('period_to');
        $table->integer('row_count')->default(0);
        $table->enum('status', ['processing', 'completed', 'failed'])->default('processing');
        $table->text('error_message')->nullable();
        $table->timestamps();
    });
}

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('sales_uploads');
    }
};
