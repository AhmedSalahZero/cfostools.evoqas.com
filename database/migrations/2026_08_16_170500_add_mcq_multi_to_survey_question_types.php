<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $this->alterQuestionTypeEnum(['mcq', 'mcq_multi', 'yes_no', 'rating', 'short_text', 'number', 'dropdown']);
    }

    public function down(): void
    {
        $this->alterQuestionTypeEnum(['mcq', 'yes_no', 'rating', 'short_text', 'number', 'dropdown']);
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
        DB::statement("ALTER TABLE question_bank_items MODIFY COLUMN question_type ENUM({$enum}) NOT NULL");
    }
};
