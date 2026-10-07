<?php

namespace App\Services;

use App\Models\StudyOpeningBalance;
use Illuminate\Support\Facades\DB;

class StudyOpeningBalanceSync
{
    /**
     * Trading / RM opening value left at 0 is units × unit cost (matches the COGS screen hint).
     */
    public static function autoFillCogsOpeningValues(array $cogsData): array
    {
        foreach ($cogsData as &$cog) {
            $value = (float) ($cog['beginning_inventory_value'] ?? 0);
            $units = (float) ($cog['beginning_inventory_units'] ?? 0);
            $cost  = (float) ($cog['unit_purchase_cost'] ?? 0);
            if ($value <= 0 && $units > 0 && $cost > 0) {
                $cog['beginning_inventory_value'] = $units * $cost;
            }

            foreach ($cog['raw_materials'] ?? [] as $i => $rm) {
                $rmVal = (float) ($rm['beg_inventory_value'] ?? 0);
                $rmQty = (float) ($rm['beg_inventory_qty'] ?? 0);
                $rmCpu = (float) ($rm['cost_per_unit'] ?? 0);
                if ($rmVal <= 0 && $rmQty > 0 && $rmCpu > 0) {
                    $cog['raw_materials'][$i]['beg_inventory_value'] = $rmQty * $rmCpu;
                }
            }
        }
        unset($cog);

        return $cogsData;
    }

    /**
     * Inventory rows derived from products + COGS / sales projection (single source of truth).
     */
    public static function inventoryRowsFromStudy(object $study): array
    {
        $products = self::decode($study->products ?? '[]');
        $cogsData = self::decode($study->cogs_data ?? '[]');
        $projections = self::decode($study->projections ?? '{}');
        $salesProducts = $projections['products']
            ?? $projections['sales']['products']
            ?? [];

        $rows = [];
        foreach ($products as $pi => $prod) {
            $nature = $prod['nature'] ?? null;
            $name = $prod['name'] ?? 'Product';

            if ($nature === 'manufacturing') {
                $salesProd = self::findByName($salesProducts, $name) ?? $salesProducts[$pi] ?? [];
                $rows[] = [
                    'type' => 'manufacturing_fg',
                    'label' => "{$name} — Finished Goods Beginning Inventory",
                    'amount' => (float) ($salesProd['beg_inv_amount'] ?? 0),
                ];

                $cogsProd = self::findByName($cogsData, $name) ?? $cogsData[$pi] ?? [];
                foreach ($cogsProd['raw_materials'] ?? [] as $rm) {
                    $rmQty = (float) ($rm['beg_inventory_qty'] ?? 0);
                    $rmVal = (float) ($rm['beg_inventory_value'] ?? 0)
                        ?: ($rmQty * (float) ($rm['cost_per_unit'] ?? 0));
                    $rmName = $rm['name'] ?? 'Raw Material';
                    $rows[] = [
                        'type' => 'manufacturing_rm',
                        'label' => "{$rmName} ({$name}) — RM Beginning Inventory",
                        'amount' => $rmVal,
                    ];
                }
            } elseif ($nature === 'trading') {
                $cogsProd = self::findByName($cogsData, $name) ?? $cogsData[$pi] ?? [];
                $units = (float) ($cogsProd['beginning_inventory_units'] ?? 0);
                $value = (float) ($cogsProd['beginning_inventory_value'] ?? 0)
                    ?: ($units * (float) ($cogsProd['unit_purchase_cost'] ?? 0));
                $rows[] = [
                    'type' => 'trading',
                    'label' => "{$name} — Trading Inventory",
                    'amount' => $value,
                ];
            }
        }

        return $rows;
    }

    public static function applyInventory(int $studyId): void
    {
        $study = DB::table('financial_studies')->where('id', $studyId)->first();
        $ob = StudyOpeningBalance::where('financial_study_id', $studyId)->first();
        if (!$study || !$ob) {
            return;
        }

        $ob->inventory = self::inventoryRowsFromStudy($study);
        $ob->computeTotals();
        $ob->save();
        self::writeStudyJson($studyId, $ob);
    }

    public static function writeStudyJson(int $studyId, StudyOpeningBalance $ob): void
    {
        DB::table('financial_studies')
            ->where('id', $studyId)
            ->update([
                'opening_balance' => json_encode(self::toEnginePayload($ob)),
                'updated_at' => now(),
            ]);
    }

    public static function toEnginePayload(StudyOpeningBalance $ob): array
    {
        return [
            'source' => 'manual',
            'as_of_date' => $ob->as_of_date?->format('Y-m-d'),
            'notes' => $ob->notes,
            'cash_bank' => (float) $ob->cash_bank,
            'paid_up_capital' => (float) $ob->paid_up_capital,
            'legal_reserve' => (float) $ob->legal_reserve,
            'retained_earnings' => (float) $ob->retained_earnings,
            'fixed_assets' => $ob->fixed_assets ?? [],
            'inventory' => $ob->inventory ?? [],
            'current_assets' => $ob->current_assets ?? [],
            'other_non_current' => $ob->other_non_current ?? [],
            'long_term_liabilities' => $ob->long_term_liabilities ?? [],
            'current_liabilities' => $ob->current_liabilities ?? [],
            'equity' => $ob->equity ?? [],
            'totals' => [
                'gross_fa' => (float) $ob->total_gross_fa,
                'accum_dep' => (float) $ob->total_accum_dep,
                'net_fa' => (float) $ob->total_net_fa,
                'inventory' => (float) $ob->total_inventory,
                'current_assets' => (float) $ob->total_current_assets,
                'other_non_current' => (float) $ob->total_other_non_current,
                'long_term_liabilities' => (float) $ob->total_long_term_liabilities,
                'current_liabilities' => (float) $ob->total_current_liabilities,
                'equity' => (float) $ob->total_equity,
                'total_assets' => (float) $ob->total_assets,
                'total_liabilities' => (float) $ob->total_liabilities,
            ],
            'is_balanced' => (bool) $ob->is_balanced,
        ];
    }

    private static function decode(null|string|array $value): array
    {
        if (is_array($value)) {
            return $value;
        }
        $decoded = json_decode($value ?? '[]', true);

        return is_array($decoded) ? $decoded : [];
    }

    private static function findByName(array $rows, string $name): ?array
    {
        foreach ($rows as $row) {
            if (($row['name'] ?? null) === $name) {
                return $row;
            }
        }

        return null;
    }
}
