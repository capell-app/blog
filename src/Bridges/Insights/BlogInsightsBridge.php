<?php

declare(strict_types=1);

namespace Capell\Blog\Bridges\Insights;

use Capell\Core\Facades\CapellCore;
use Capell\Insights\Enums\InsightsEventType;
use Capell\Insights\Models\InsightsEvent;
use DateTimeInterface;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * The only place Blog reads Insights analytics for its dashboard widgets. Each method returns null
 * unless Insights is available, so a widget can show its empty state.
 */
final class BlogInsightsBridge
{
    /** @return Collection<int, InsightsEvent>|null The five most viewed paths in the range, or null without Insights. */
    public static function topPages(DateTimeInterface $rangeStart, DateTimeInterface $rangeEnd): ?Collection
    {
        if (! CapellCore::isPackageAvailable('capell-app/insights')) {
            return null;
        }

        return InsightsEvent::query()
            ->select('path', DB::raw('COUNT(*) as views'))
            ->where('type', InsightsEventType::PageView)
            ->where('occurred_at', '>=', $rangeStart)
            ->where('occurred_at', '<=', $rangeEnd)
            ->groupBy('path')
            ->orderByDesc('views')
            ->limit(5)
            ->get();
    }

    /** @return Collection<int, InsightsEvent>|null Daily page views and distinct visitors in the range, or null without Insights. */
    public static function dailyTraffic(DateTimeInterface $rangeStart, DateTimeInterface $rangeEnd): ?Collection
    {
        if (! CapellCore::isPackageAvailable('capell-app/insights')) {
            return null;
        }

        return InsightsEvent::query()
            ->select(
                DB::raw('DATE(occurred_at) as date'),
                DB::raw('COUNT(*) as views'),
                DB::raw('COUNT(DISTINCT visit_id) as visitors'),
            )
            ->where('type', InsightsEventType::PageView)
            ->where('occurred_at', '>=', $rangeStart)
            ->where('occurred_at', '<=', $rangeEnd)
            ->groupBy(DB::raw('DATE(occurred_at)'))
            ->orderBy('date')
            ->get();
    }
}
