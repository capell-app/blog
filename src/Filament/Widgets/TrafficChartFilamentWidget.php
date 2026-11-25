<?php

declare(strict_types=1);

namespace Capell\Blog\Filament\Widgets;

use Capell\Admin\Contracts\CapellFilamentWidgetContract;
use Capell\Admin\Filament\Concerns\GatedByRoleAndSettings;
use Capell\Admin\Filament\Concerns\HasDashboardDateRange;
use Capell\Blog\Bridges\Insights\BlogInsightsBridge;
use Capell\Blog\Data\Dashboard\TrafficChartData;
use Capell\Blog\Data\Dashboard\TrafficPointData;
use Filament\Widgets\Widget;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use Override;

final class TrafficChartFilamentWidget extends Widget implements CapellFilamentWidgetContract
{
    use GatedByRoleAndSettings;
    use HasDashboardDateRange;

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
        [$rangeStart, $rangeEnd] = $this->getDashboardDateRange();
        $rows = BlogInsightsBridge::dailyTraffic($rangeStart, $rangeEnd);

        if (! $rows instanceof Collection) {
            return new TrafficChartData(
                totalViews: 0,
                totalVisitors: 0,
                points: TrafficPointData::collect([], Collection::class),
            );
        }

        $points = $rows->map(fn (Model $row): TrafficPointData => new TrafficPointData(
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
