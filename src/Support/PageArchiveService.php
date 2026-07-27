<?php

declare(strict_types=1);

namespace Capell\Blog\Support;

use Capell\Blog\Actions\ApplyArchiveDateFilterAction;
use Capell\Blog\Data\ArchiveMonthData;
use Capell\Blog\Enums\CacheEnum;
use Capell\Blog\Models\Article;
use Capell\Core\Enums\Database\DatabaseDateOperation;
use Capell\Core\Facades\CapellCore;
use Capell\Core\Facades\CapellDatabase;
use Capell\Core\Models\Language;
use Capell\Core\Models\Site;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use stdClass;

class PageArchiveService
{
    /**
     * Returns archive counts grouped by year and month, optionally paginated.
     *
     * @param  bool  $paginate  Whether to paginate the results
     * @param  int|null  $perPage  Number of items per page if paginating
     * @return LengthAwarePaginator<int, ArchiveMonthData>|Collection<int, ArchiveMonthData>
     */
    public function getArchivedCountsByMonth(
        Site $site,
        Language $language,
        string $group,
        bool $paginate = false,
        ?int $perPage = null,
        ?string $paginationKey = null,
    ): LengthAwarePaginator|Collection {
        if (! $paginate) {
            $version = Cache::store()->get(CacheEnum::archivesVersion($site->id, $language->id), 0);
            $version = is_numeric($version) ? (int) $version : 0;
            $cacheKey = CacheEnum::archives($site->id, $language->id, $group, $perPage, null, $version);

            $archives = CapellCore::rememberCache(
                $cacheKey,
                function () use ($site, $language, $group, $perPage, $paginationKey): Collection {
                    $archives = $this->queryArchivedCountsByMonth($site, $language, $group, false, $perPage, $paginationKey);

                    return $archives instanceof Collection ? $archives : new Collection($archives->items());
                },
            );

            return $archives
                ->map(fn (ArchiveMonthData|array $archive): ArchiveMonthData => ArchiveMonthData::from($archive));
        }

        return $this->queryArchivedCountsByMonth($site, $language, $group, $paginate, $perPage, $paginationKey);
    }

    /**
     * @return LengthAwarePaginator<int, ArchiveMonthData>|Collection<int, ArchiveMonthData>
     */
    private function queryArchivedCountsByMonth(
        Site $site,
        Language $language,
        string $group,
        bool $paginate,
        ?int $perPage,
        ?string $paginationKey,
    ): LengthAwarePaginator|Collection {
        $query = Article::query();
        $dialect = CapellDatabase::for($query->getModel())->queryDialect();
        $publishedAt = (new ApplyArchiveDateFilterAction)->publishedAt($query);
        $year = $dialect->date(DatabaseDateOperation::Year, $publishedAt);
        $month = $dialect->date(DatabaseDateOperation::Month, $publishedAt);

        $query
            ->selectRaw('COUNT(*) as total')
            ->selectRaw($year->sql . ' as year', $year->bindings)
            ->selectRaw($month->sql . ' as month', $month->bindings)
            ->whereHas(
                'blueprint',
                function (Builder $query) use ($group): void {
                    $query->where('group', $group)->enabled()->visible();
                },
            )
            ->whereHas(
                'translation',
                function (Builder $query) use ($language): void {
                    $query->where('language_id', $language->id);
                },
            )
            ->where('site_id', $site->id)
            ->publishedDate()
            ->groupByRaw($year->sql . ', ' . $month->sql, [...$year->bindings, ...$month->bindings])
            ->orderByRaw($year->sql . ' DESC', $year->bindings)
            ->orderByRaw($month->sql . ' DESC', $month->bindings);

        if ($paginate) {
            $paginator = $query->getQuery()->paginate($perPage ?? 15, pageName: $paginationKey ?? 'page');

            $paginator->getCollection()->transform(fn (stdClass $row): ArchiveMonthData => new ArchiveMonthData(
                (int) $row->year,
                (int) $row->month,
                (int) $row->total,
            ));

            return $paginator;
        }

        return $query->getQuery()
            ->get()
            ->map(
                fn (stdClass $row): ArchiveMonthData => new ArchiveMonthData(
                    (int) $row->year,
                    (int) $row->month,
                    (int) $row->total,
                ),
            );
    }
}
