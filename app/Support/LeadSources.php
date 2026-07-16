<?php

namespace App\Support;

use App\Models\PortfolioCompany;

class LeadSources
{
    public const DEFAULT = [
        'Direct Contact',
        'Referral',
        'Website',
        'Social Media',
        'Advertisement',
        'Event',
    ];

    public static function optionsForOrganization(?int $organizationId): array
    {
        $existing = PortfolioCompany::query()
            ->when($organizationId, fn ($q) => $q->where('organization_id', $organizationId))
            ->whereNotNull('lead_source')
            ->where('lead_source', '!=', '')
            ->distinct()
            ->orderBy('lead_source')
            ->pluck('lead_source')
            ->all();

        return array_values(array_unique(array_merge(self::DEFAULT, $existing)));
    }
}
