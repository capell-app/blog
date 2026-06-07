<?php

declare(strict_types=1);

namespace Capell\Blog\Filament\Widgets;

use Capell\Admin\Contracts\CapellWidgetContract;
use Capell\Admin\Filament\Concerns\GatedByRoleAndSettings;
use Capell\Admin\Filament\Concerns\HasDashboardDateRange;
use Capell\Blog\Data\Dashboard\TopPageData;
use Capell\Blog\Data\Dashboard\TopPagesData;
use Capell\Insights\Enums\InsightsEventType;
use Capell\Insights\Models\InsightsEvent;
use Filament\Widgets\Widget;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Override;

final class TopPagesWidgetAbstract extends Widget implements CapellWidgetContract
{
    use GatedByRoleAndSettings;
    use HasDashboardDateRange;

    private const string INSIGHTS_EVENT = InsightsEvent::class;

    private const string INSIGHTS_EVENT_TYPE = InsightsEventType::class;

    protected static string $settingsKey = 'top_pages';

    /** @var list<string> */
    protected static array $rolesConfigKeys = ['admin', 'super_admin'];

    protected string $view = 'capell-blog::filament.widgets.top-pages';

    /** @var int|string|array<string, int|null> */
    protected int|string|array $columnSpan = ['md' => 1];

    /**
     * @return array<string, mixed>
     */
    #[Override]
    protected function getViewData(): array
    {
        return ['data' => $this->getData()];
    }

    private function getData(): TopPagesData
    {
        $insightsEventClass = self::INSIGHTS_EVENT;
        $insightsEventTypeClass = self::INSIGHTS_EVENT_TYPE;

        if (! class_exists($insightsEventClass) || ! enum_exists($insightsEventTypeClass)) {
            return new TopPagesData(
                pages: TopPageData::collect([], Collection::class),
            );
        }

        [$rangeStart, $rangeEnd] = $this->getDashboardDateRange();
        $pageViewEventType = constant($insightsEventTypeClass . '::PageView');

        $rows = $insightsEventClass::query()
            ->select('path', DB::raw('COUNT(*) as views'))
            ->where('type', $pageViewEventType)
            ->where('occurred_at', '>=', $rangeStart)
            ->where('occurred_at', '<=', $rangeEnd)
            ->groupBy('path')
            ->orderByDesc('views')
            ->limit(5)
            ->get();

        $pages = $rows->map(fn (object $row): TopPageData => new TopPageData(
            path: (string) $row->path,
            views: (int) $row->views,
        ));

        return new TopPagesData(
            pages: TopPageData::collect($pages, Collection::class),
        );
    }
}
