<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasColumn('projects', 'contract_service_id')) {
            Schema::table('projects', function (Blueprint $table) {
                $table->foreignId('contract_service_id')
                    ->nullable()
                    ->after('portfolio_company_id')
                    ->constrained('contract_services')
                    ->nullOnDelete();
            });
        }

        if (!Schema::hasTable('contract_service_milestones')) {
            Schema::create('contract_service_milestones', function (Blueprint $table) {
                $table->id();
                $table->foreignId('contract_service_id')->constrained()->onDelete('cascade');
                $table->unsignedTinyInteger('milestone_index');
                $table->decimal('execution_percentage', 8, 2)->default(0);
                $table->decimal('amount', 15, 2)->default(0);
                $table->date('start_date')->nullable();
                $table->date('end_date')->nullable();
                $table->unsignedSmallInteger('collection_days')->default(0);
                $table->timestamps();

                $table->unique(['contract_service_id', 'milestone_index'], 'cs_milestones_svc_idx_unique');
            });
        } elseif (!$this->hasIndex('contract_service_milestones', 'cs_milestones_svc_idx_unique')) {
            Schema::table('contract_service_milestones', function (Blueprint $table) {
                $table->unique(['contract_service_id', 'milestone_index'], 'cs_milestones_svc_idx_unique');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('contract_service_milestones');

        if (Schema::hasColumn('projects', 'contract_service_id')) {
            Schema::table('projects', function (Blueprint $table) {
                $table->dropConstrainedForeignId('contract_service_id');
            });
        }
    }

    private function hasIndex(string $table, string $index): bool
    {
        $row = DB::selectOne(
            'SELECT COUNT(*) AS c FROM information_schema.statistics
             WHERE table_schema = DATABASE() AND table_name = ? AND index_name = ?',
            [$table, $index]
        );

        return ($row->c ?? 0) > 0;
    }
};
