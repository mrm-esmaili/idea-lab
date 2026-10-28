<?php

namespace App\Services;

use App\Models\Campaign;
use Illuminate\Support\Collection;

/**
 * گزارش کمپین‌ها بر اساس قاعده انتساب App\Models\Campaign.
 *
 * علاوه بر شاخص هر کمپین، ترافیکی را برمی‌گرداند که به هیچ کمپین ثبت‌شده‌ای
 * نسبت داده نمی‌شود: بدون utm_campaign (مستقیم/ارگانیک) یا با کدی که
 * ثبت نشده (مثلاً اشتباه تایپی در لینک تبلیغ).
 */
class CampaignStatsService
{
    public function __construct(
        private readonly FunnelStats $funnel,
    ) {}

    /**
     * @param  Collection<int, Campaign>  $campaigns
     * @return array{campaigns: Collection<int, array>, other: Collection<int, array>}
     */
    public function report(Collection $pageIds, Collection $campaigns): array
    {
        $grouped = $this->funnel->grouped($pageIds->values(), byCampaign: true);

        $registeredKeys = $campaigns
            ->map(fn (Campaign $campaign) => FunnelStats::key($campaign->landing_page_id, $campaign->slug))
            ->flip();

        $byCampaign = $campaigns->mapWithKeys(function (Campaign $campaign) use ($grouped) {
            $key = FunnelStats::key($campaign->landing_page_id, $campaign->slug);
            $stats = $grouped->get($key) ?? $this->empty($campaign->landing_page_id, $campaign->slug);

            $stats['cost_per_lead_rial'] = $campaign->budget_rial && $stats['leads'] > 0
                ? intdiv($campaign->budget_rial, $stats['leads'])
                : null;

            return [$campaign->id => $stats];
        });

        $other = $grouped
            ->reject(fn ($stats, $key) => $registeredKeys->has($key))
            ->sortByDesc('page_views')
            ->values();

        return ['campaigns' => $byCampaign, 'other' => $other];
    }

    private function empty(int $pageId, ?string $code): array
    {
        return array_fill_keys(FunnelStats::SUM_KEYS, 0) + [
            'landing_page_id' => $pageId,
            'campaign' => $code,
            'lead_rate' => 0.0,
        ];
    }
}
