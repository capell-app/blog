<?php

declare(strict_types=1);

namespace Capell\Blog\Filament\Widgets;

use Capell\Admin\Contracts\CapellFilamentWidgetContract;
use Capell\Admin\Filament\Concerns\GatedByRoleAndSettings;
use Capell\Admin\Filament\Concerns\HasDashboardDateRange;
use Capell\Blog\Data\Dashboard\TrafficChartData;
use Capell\Blog\Data\Dashboard\TrafficPointData;
use Capell\Insights\Enums\InsightsEventType;
use Capell\Insights\Models\InsightsEvent;
use Filament\Widgets\Widget;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Override;

final class TrafficChartFilamentWidget extends Widget implements CapellFilamentWidgetContract
{
    use GatedByRoleAndSettings;
    use HasDashboardDateRange;

    private const string INSIGHTS_EVENT = InsightsEvent::class;

    private const string INSIGHTS_EVENT_TYPE = InsightsEventType::class;

    protected static string $settingsKey = 'traffic_chart';

    /** @var list<string> */
    protected static array $rolesConfigKeys = ['admin', 'super_admin'];

    protected string $view = 'capell-blog::filament.widgets.traffic-chart';

    /** @var int|string|array<string, int|null> */
    protected int|string|array $columnSpan = 'full';

    /**
     * @return array<string, mixed>
     */
    #[Override]
    protected function getViewData(): array
    {
        return ['data' => $this->getData()];
    }

    private function getData(): TrafficChartData
    {
        $insightsEventClass = self::INSIGHTS_EVENT;
        $insightsEventTypeClass = self::INSIGHTS_EVENT_TYPE;

        if (! class_exists($insightsEventClass) || ! enum_exists($insightsEventTypeClass)) {
            return new TrafficChartData(
                totalViews: 0,
                totalVisitors: 0,
                points: TrafficPointData::collect([], Collection::class),
            );
        }

        [$rangeStart, $rangeEnd] = $this->getDashboardDateRange();
        $pageViewEventType = constant($insightsEventTypeClass . '::PageView');

        $rows = $insightsEventClass::query()
            ->select(
                DB::raw('DATE(occurred_at) as date'),
                DB::raw('COUNT(*) as views'),
                DB::raw('COUNT(DISTINCT visit_id) as visitors'),
            )
            ->where('type', $pageViewEventType)
            ->where('occurred_at', '>=', $rangeStart)
            ->where('occurred_at', '<=', $rangeEnd)
            ->groupBy(DB::raw('DATE(occurred_at)'))
            ->orderBy('date')
            ->get();

        $points = $rows->map(fn (object $row): TrafficPointData => new TrafficPointData(
            date: (string) $row->date,
            views: (int) $row->views,
            visitors: (int) $row->visitors,
        ));

        return new TrafficChartData(
            totalViews: (int) $rows->sum('views'),
            totalVisitors: (int) $rows->sum('visitors'),
            points: TrafficPointData::collect($points, Collection::class),
        );
    }
}
