<?php

declare(strict_types=1);

namespace Capell\Blog\Livewire\Page;

use Capell\Blog\Actions\ApplyArchiveDateFilterAction;
use Capell\Blog\Actions\BuildBlogResultsViewDataAction;
use Capell\Blog\Data\BlogResultsViewData;
use Capell\Blog\Enums\ResourceEnum;
use Capell\Blog\Models\Article;
use Capell\Core\Models\Blueprint;
use Capell\Core\Models\Language;
use Capell\Core\Models\Page;
use Capell\Core\Models\Site;
use Capell\Frontend\Data\PageListingRequestData;
use Capell\Frontend\Facades\Frontend;
use Capell\Frontend\Livewire\Page\AbstractPage;
use Capell\Frontend\Support\Loader\PageLoader;
use Capell\Frontend\Support\State\FrontendState;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Date;
use Override;

class Archive extends AbstractPage
{
    public ?int $month = null;

    public ?int $year = null;

    protected static string $defaultView = 'capell-blog::livewire.page.results';

    protected ?BlogResultsViewData $blogResultsViewData = null;

    protected function setup(): void
    {
        if (($this->year === null || $this->year === 0) && ($this->month === null || $this->month === 0)) {
            [$this->year, $this->month] = $this->getArchiveDateFromUrl();
        }

        abort_if(($this->year === null || $this->year === 0) && ($this->month === null || $this->month === 0), 404);

        $page = Frontend::page();
        $language = Frontend::language();
        $site = Frontend::site();

        abort_unless($page instanceof Page && $language instanceof Language && $site instanceof Site, 404);

        $blueprint = $page->blueprint;

        abort_unless($blueprint instanceof Blueprint, 404);

        $preparedResults = Frontend::getFrontendData('blog.results');
        $preparedViewData = Frontend::getFrontendData('blog.results_view_data');

        if (
            ($preparedResults instanceof Collection || $preparedResults instanceof LengthAwarePaginator)
            && $preparedViewData instanceof BlogResultsViewData
        ) {
            abort_if($preparedResults->isEmpty(), 404);

            $this->results = $preparedResults;
            $this->blogResultsViewData = $preparedViewData;
            $this->params = $this->getReplacementData();

            resolve(FrontendState::class)->withParams($this->params);

            return;
        }

        $configuredPaginationKey = config('capell-admin.page_query', 'pageQuery');
        $paginationKey = is_string($configuredPaginationKey) ? $configuredPaginationKey : 'pageQuery';
        $configuredLimit = $page->meta['limit']
            ?? $blueprint->meta['limit']
            ?? config('capell-frontend.pagination_limit', 12);
        $limit = is_numeric($configuredLimit) ? (int) $configuredLimit : 12;
        $typeKey = is_string($blueprint->meta['page_group'] ?? null)
            ? $blueprint->meta['page_group']
            : strtolower(ResourceEnum::Article->name);

        $this->results = PageLoader::list(new PageListingRequestData(
            language: $language,
            site: $site,
            limit: $limit,
            paginationPage: (int) $this->getPage($paginationKey),
            typeKey: $typeKey,
            withImage: (bool) ($blueprint->meta['with_image'] ?? false),
            withPagination: (bool) ($blueprint->meta['pagination'] ?? true),
            withDate: (bool) ($blueprint->meta['with_date'] ?? false),
            paginationKey: 'article-archives',
            cacheKeySuffix: sprintf('year-%s-month-%s', $this->year, $this->month),
            morphModel: Article::class,
            modifyQuery: function (Builder $query): void {
                ApplyArchiveDateFilterAction::run($query, $this->year, $this->month);
            },
        ));

        abort_if($this->results->isEmpty(), 404);

        $this->blogResultsViewData = BuildBlogResultsViewDataAction::run($this->results);
        $this->params = $this->getReplacementData();

        resolve(FrontendState::class)->withParams($this->params);
    }

    /**
     * @return array{0: int, 1: int|null}
     */
    protected function getArchiveDateFromUrl(): array
    {
        $params = Frontend::params();
        $date = $params['date'] ?? '';

        $month = null;
        $year = null;
        abort_if($date === '' || $date === '0', 404);

        $parts = explode('/', (string) $date);
        $dates = explode('-', $parts[0]);

        if (isset($dates[0]) && mb_strlen($dates[0]) === 4 && is_numeric($dates[0])) {
            $year = (int) $dates[0];
        }

        if (isset($dates[1]) && is_numeric($dates[1]) && (int) $dates[1] >= 1 && (int) $dates[1] <= 12) {
            $month = (int) $dates[1];
        }

        abort_if($year === 0 || $year === null, 404);

        return [$year, $month];
    }

    /**
     * @return array<string, mixed>
     */
    #[Override]
    protected function getViewData(): array
    {
        $year = $this->year;

        abort_if($year === null, 404);

        $date = Date::create()->day(1)->month($this->month ?? 1)->year($year);

        return [
            'archive_date' => $date,
            'archive_month' => $date->format('F'),
            'archive_year' => $year,
            'blogResultsViewData' => $this->blogResultsViewData ?? BuildBlogResultsViewDataAction::run($this->results),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    protected function getReplacementData(): array
    {
        $year = $this->year;

        abort_if($year === null, 404);

        $date = Date::create()->day(1)->month($this->month ?? 1)->year($year);

        return [
            'archive_date' => $date,
            'archive_month' => $date->format('F'),
            'archive_year' => $year,
        ];
    }
}
