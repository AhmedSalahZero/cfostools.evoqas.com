<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // ── Board: user-renamable label for the new third section ──────
        // Null = show the default "Discussed Direction" label.
        Schema::table('business_radar_boards', function (Blueprint $table) {
            $table->string('direction_label')->nullable()->after('description');
        });

        // ── Items: allow a third type, and add real calendar dates ─────
        // (duration_months / impact scoring stays as-is and still drives
        // the same order-by-weight-and-speed logic as Challenges/Potentials.)
        $this->alterItemTypeEnum(['challenge', 'potential', 'direction']);

        Schema::table('business_radar_items', function (Blueprint $table) {
            $table->date('es_date')->nullable()->after('duration_months'); // Estimated Start
            $table->date('ef_date')->nullable()->after('es_date');          // Estimated Finish
        });

        // ── Direction ⇄ Challenge/Potential links ───────────────────────
        // A Discussed Direction item can reference several Challenges
        // and/or Potentials. Kept as its own table (rather than reusing
        // business_radar_links) since that table's two FK columns are
        // named/constrained specifically for the challenge↔potential pair.
        Schema::create('business_radar_direction_links', function (Blueprint $table) {
            $table->id();
            $table->foreignId('direction_item_id')
                  ->constrained('business_radar_items')
                  ->cascadeOnDelete();
            $table->foreignId('linked_item_id')
                  ->constrained('business_radar_items')
                  ->cascadeOnDelete();
            $table->string('note')->nullable();
            $table->timestamps();

            $table->unique(['direction_item_id', 'linked_item_id'], 'br_direction_links_unique_pair');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('business_radar_direction_links');

        Schema::table('business_radar_items', function (Blueprint $table) {
            $table->dropColumn(['es_date', 'ef_date']);
        });

        // Remove any direction items before shrinking the enum back down,
        // so no row is left holding a value the enum no longer allows.
        DB::table('business_radar_items')->where('type', 'direction')->delete();
        $this->alterItemTypeEnum(['challenge', 'potential']);

        Schema::table('business_radar_boards', function (Blueprint $table) {
            $table->dropColumn('direction_label');
        });
    }

    /**
     * @param  list<string>  $values
     */
    private function alterItemTypeEnum(array $values): void
    {
        $driver = Schema::getConnection()->getDriverName();
        if (! in_array($driver, ['mysql', 'mariadb'], true)) {
            return;
        }

        $enum = collect($values)
            ->map(fn (string $value) => "'{$value}'")
            ->implode(',');

        DB::statement("ALTER TABLE business_radar_items MODIFY COLUMN type ENUM({$enum}) NOT NULL");
    }
};
