<?php

namespace Tests\Unit;

use App\Models\StudyOpeningBalance;
use PHPUnit\Framework\TestCase;

class StudyOpeningBalanceComputeTotalsTest extends TestCase
{
    public function test_balanced_cash_plus_inventory_against_capital_and_reserve(): void
    {
        $ob = $this->makeOb([
            'cash_bank' => 20_000_000,
            'paid_up_capital' => 66_520_000,
            'legal_reserve' => 1_000_000,
            'inventory' => [['label' => 'Machines', 'amount' => 47_520_000]],
        ]);
        $ob->computeTotals();

        $this->assertSame(67_520_000.0, (float) $ob->total_assets);
        $this->assertSame(0.0, (float) $ob->total_liabilities);
        $this->assertSame(67_520_000.0, (float) $ob->total_equity);
        $this->assertTrue($ob->is_balanced);
    }

    public function test_cash_equals_paid_up_capital_balances(): void
    {
        $ob = $this->makeOb([
            'cash_bank' => 20_000_000,
            'paid_up_capital' => 20_000_000,
        ]);
        $ob->computeTotals();

        $this->assertSame(20_000_000.0, (float) $ob->total_assets);
        $this->assertSame(20_000_000.0, (float) $ob->total_equity);
        $this->assertTrue($ob->is_balanced);
    }

    public function test_inventory_equals_paid_up_capital_with_zero_cash_balances(): void
    {
        $ob = $this->makeOb([
            'cash_bank' => 0,
            'paid_up_capital' => 10_000_000,
            'inventory' => [['label' => 'Stock', 'amount' => 10_000_000]],
        ]);
        $ob->computeTotals();

        $this->assertSame(10_000_000.0, (float) $ob->total_assets);
        $this->assertSame(10_000_000.0, (float) $ob->total_equity);
        $this->assertTrue($ob->is_balanced);
    }

    public function test_unbalanced_payable_is_off_by_exactly_the_payable(): void
    {
        $ob = $this->makeOb([
            'cash_bank' => 20_000_000,
            'paid_up_capital' => 66_520_000,
            'legal_reserve' => 1_000_000,
            'inventory' => [['label' => 'Machines', 'amount' => 47_520_000]],
            'current_liabilities' => [['label' => 'AP', 'amount' => 4_000_000]],
        ]);
        $ob->computeTotals();

        $diff = (float) $ob->total_assets - ((float) $ob->total_liabilities + (float) $ob->total_equity);
        $this->assertSame(67_520_000.0, (float) $ob->total_assets);
        $this->assertSame(4_000_000.0, (float) $ob->total_liabilities);
        $this->assertSame(67_520_000.0, (float) $ob->total_equity);
        $this->assertEqualsWithDelta(-4_000_000.0, $diff, 0.01);
        $this->assertFalse($ob->is_balanced);
    }

    public function test_extra_equity_row_and_other_non_current_are_included(): void
    {
        $ob = $this->makeOb([
            'cash_bank' => 1_000_000,
            'paid_up_capital' => 5_000_000,
            'legal_reserve' => 500_000,
            'retained_earnings' => 200_000,
            'other_non_current' => [['label' => 'Deposit', 'amount' => 250_000]],
            'current_assets' => [['label' => 'AR', 'amount' => 4_000_000]],
            'equity' => [['label' => 'Share premium', 'amount' => 550_000]],
        ]);
        $ob->computeTotals();

        $this->assertSame(5_250_000.0, (float) $ob->total_assets);
        $this->assertSame(6_250_000.0, (float) $ob->total_equity);
        $this->assertFalse($ob->is_balanced);
    }

    private function makeOb(array $attrs): StudyOpeningBalance
    {
        $ob = new StudyOpeningBalance();
        $ob->cash_bank = $attrs['cash_bank'] ?? 0;
        $ob->paid_up_capital = $attrs['paid_up_capital'] ?? 0;
        $ob->legal_reserve = $attrs['legal_reserve'] ?? 0;
        $ob->retained_earnings = $attrs['retained_earnings'] ?? 0;
        $ob->fixed_assets = $attrs['fixed_assets'] ?? [];
        $ob->inventory = $attrs['inventory'] ?? [];
        $ob->current_assets = $attrs['current_assets'] ?? [];
        $ob->other_non_current = $attrs['other_non_current'] ?? [];
        $ob->long_term_liabilities = $attrs['long_term_liabilities'] ?? [];
        $ob->current_liabilities = $attrs['current_liabilities'] ?? [];
        $ob->equity = $attrs['equity'] ?? [];

        return $ob;
    }
}
