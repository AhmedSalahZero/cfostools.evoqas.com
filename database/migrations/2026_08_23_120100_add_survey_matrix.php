<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $this->alterQuestionTypeEnum([
            'mcq', 'mcq_multi', 'yes_no', 'rating', 'short_text', 'number', 'dropdown', 'matrix',
        ]);

        Schema::create('survey_matrix_rows', function (Blueprint $table) {
            $table->id();
            $table->foreignId('survey_question_id')->constrained('survey_questions')->cascadeOnDelete();
            $table->string('row_text');
            $table->integer('sort_order')->default(0);
            $table->timestamps();
        });

        Schema::table('survey_answers', function (Blueprint $table) {
            $table->foreignId('matrix_row_id')
                ->nullable()
                ->after('survey_question_id')
                ->constrained('survey_matrix_rows')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('survey_answers', function (Blueprint $table) {
            $table->dropConstrainedForeignId('matrix_row_id');
        });

        Schema::dropIfExists('survey_matrix_rows');

        $this->alterQuestionTypeEnum([
            'mcq', 'mcq_multi', 'yes_no', 'rating', 'short_text', 'number', 'dropdown',
        ]);
    }

    /**
     * @param  list<string>  $values
     */
    private function alterQuestionTypeEnum(array $values): void
    {
        $driver = Schema::getConnection()->getDriverName();
        if (! in_array($driver, ['mysql', 'mariadb'], true)) {
            return;
        }

        $enum = collect($values)
            ->map(fn (string $value) => "'{$value}'")
            ->implode(',');

        DB::statement("ALTER TABLE survey_questions MODIFY COLUMN question_type ENUM({$enum}) NOT NULL");
    }
};
