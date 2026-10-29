<?php

namespace App\Services;

use App\Models\Idea;
use Illuminate\Support\Collection;

/**
 * شاخص‌های عملکرد صفحات فرود یک ایده؛ تعاریف در FunnelStats.
 */
class IdeaStatsService
{
    public function __construct(
        private readonly FunnelStats $funnel,
    ) {}

    /**
     * @return Collection<int, array<string, int|float>> کلید: شناسه صفحه فرود
     */
    public function forLandingPages(Idea $idea): Collection
    {
        return $this->funnel
            ->grouped($idea->landingPages()->pluck('id'), byCampaign: false)
            ->mapWithKeys(fn ($stats, $key) => [(int) $key => $stats]);
    }

    public function totals(Collection $pageStats): array
    {
        return FunnelStats::totals($pageStats);
    }
}
