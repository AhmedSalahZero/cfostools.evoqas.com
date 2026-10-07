<?php

namespace Tests\Unit;

use App\Services\StudyOpeningBalanceSync;
use PHPUnit\Framework\TestCase;

class StudyOpeningBalanceSyncTest extends TestCase
{
    public function test_b11_auto_fills_trading_opening_value_from_units_times_cost(): void
    {
        $filled = StudyOpeningBalanceSync::autoFillCogsOpeningValues([
            [
                'name' => 'Machines',
                'nature' => 'trading',
                'beginning_inventory_units' => 4400,
                'beginning_inventory_value' => 0,
                'unit_purchase_cost' => 11000,
            ],
        ]);

        $this->assertSame(48_400_000.0, (float) $filled[0]['beginning_inventory_value']);
    }

    public function test_b11_leaves_an_explicit_non_zero_value_untouched(): void
    {
        $filled = StudyOpeningBalanceSync::autoFillCogsOpeningValues([
            [
                'beginning_inventory_units' => 10,
                'beginning_inventory_value' => 50,
                'unit_purchase_cost' => 9,
            ],
        ]);

        $this->assertSame(50.0, (float) $filled[0]['beginning_inventory_value']);
    }

    public function test_b11_auto_fills_raw_material_opening_value(): void
    {
        $filled = StudyOpeningBalanceSync::autoFillCogsOpeningValues([
            [
                'name' => 'Widget',
                'nature' => 'manufacturing',
                'raw_materials' => [[
                    'name' => 'Steel',
                    'beg_inventory_qty' => 100,
                    'beg_inventory_value' => 0,
                    'cost_per_unit' => 25,
                ]],
            ],
        ]);

        $this->assertSame(2500.0, (float) $filled[0]['raw_materials'][0]['beg_inventory_value']);
    }

    public function test_b14_inventory_rows_come_from_cogs_not_a_posted_override(): void
    {
        $study = (object) [
            'products' => json_encode([['name' => 'Machines', 'nature' => 'trading']]),
            'cogs_data' => json_encode([[
                'name' => 'Machines',
                'nature' => 'trading',
                'beginning_inventory_units' => 4400,
                'beginning_inventory_value' => 0,
                'unit_purchase_cost' => 11000,
            ]]),
            'projections' => json_encode(['products' => []]),
        ];

        $rows = StudyOpeningBalanceSync::inventoryRowsFromStudy($study);

        $this->assertCount(1, $rows);
        $this->assertSame('trading', $rows[0]['type']);
        $this->assertSame(48_400_000.0, $rows[0]['amount']);
        $this->assertStringContainsString('Machines', $rows[0]['label']);
    }

    public function test_b19_test_math_controller_is_gone(): void
    {
        $this->assertFileDoesNotExist(dirname(__DIR__, 2).'/app/Http/Controllers/TestMathController.php');
        $this->assertFalse(class_exists(\App\Http\Controllers\TestMathController::class, false));
    }

    public function test_b19_dead_opening_balance_methods_are_gone(): void
    {
        $this->assertFalse(method_exists(\App\Http\Controllers\FinancialStudyController::class, 'openingBalanceStep'));
        $this->assertFalse(method_exists(\App\Http\Controllers\FinancialStudyController::class, 'saveOpeningBalanceStep'));
    }
}
