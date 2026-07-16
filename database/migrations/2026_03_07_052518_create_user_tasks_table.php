<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('user_tasks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->foreignId('organization_id')->constrained()->onDelete('cascade');
            $table->foreignId('portfolio_company_id')->nullable()->constrained()->onDelete('set null');

            $table->string('title');
            $table->text('description')->nullable();
            $table->enum('priority', ['low', 'medium', 'high'])->default('medium');
            $table->enum('status', ['not_started', 'in_progress', 'completed', 'cancelled'])->default('not_started');

            // Expected (planned)
            $table->date('expected_start_date')->nullable();
            $table->unsignedSmallInteger('expected_duration_days')->nullable(); // due days
            $table->date('expected_end_date')->nullable();                      // computed or manual

            // Actual (tracked)
            $table->date('actual_start_date')->nullable();
            $table->unsignedSmallInteger('actual_duration_days')->nullable();
            $table->date('actual_end_date')->nullable();

            $table->boolean('reminder_enabled')->default(true);  // alert on due date
            $table->text('completion_notes')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('user_tasks');
    }
};