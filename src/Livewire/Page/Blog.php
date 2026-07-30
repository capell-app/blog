<?php

declare(strict_types=1);

namespace Capell\Blog\Livewire\Page;

use Capell\Blog\Actions\BuildBlogResultsViewDataAction;
use Capell\Blog\Data\BlogResultsViewData;
use Capell\Blog\Models\Article;
use Capell\Blog\Support\Loader\TagLoader;
use Capell\Core\Enums\PageOrderEnum;
use Capell\Core\Models\Blueprint;
use Capell\Core\Models\Language;
use Capell\Core\Models\Page;
use Capell\Core\Models\Site;
use Capell\Frontend\Data\PageListingRequestData;
use Capell\Frontend\Facades\Frontend;
use Capell\Frontend\Livewire\Page\AbstractPage;
use Capell\Frontend\Support\Loader\PageLoader;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Override;

class Blog extends AbstractPage
{
    protected static string $defaultView = 'capell-blog::livewire.page.results';

    /** @var Collection<array-key, mixed>|null */
    protected ?Collection $latestArticles = null;

    /** @var Collection<array-key, mixed>|null */
    protected ?Collection $sidebarTags = null;

    protected ?Page $tagPage = null;

    protected function setup(): void
    {
        $page = Frontend::page();
        $language = Frontend::language();
        $site = Frontend::site();

        abort_unless($page instanceof Page && $language instanceof Language && $site instanceof Site, 404);

        $blueprint = $page->blueprint;

        abort_unless($blueprint instanceof Blueprint, 404);

        $preparedResults = Frontend::getFrontendData('blog.results');
        $preparedViewData = Frontend::getFrontendData('blog.results_view_data');

        if ($preparedResults instanceof Collection || $preparedResults instanceof LengthAwarePaginator) {
            $this->results = $preparedResults;
            $preparedLatestArticles = Frontend::getFrontendData('blog.latest_articles');
            $preparedSidebarTags = Frontend::getFrontendData('blog.sidebar_tags');
            $preparedTagPage = Frontend::getFrontendData('blog.tag_page');
            $this->latestArticles = $preparedLatestArticles instanceof Collection ? $preparedLatestArticles : null;
            $this->sidebarTags = $preparedSidebarTags instanceof Collection ? $preparedSidebarTags : null;
            $this->tagPage = $preparedTagPage instanceof Page ? $preparedTagPage : null;

            return;
        }

        $configuredPaginationKey = config('capell-admin.page_query', 'pageQuery');
        $paginationKey = is_string($configuredPaginationKey) ? $configuredPaginationKey : 'pageQuery';
        $configuredLimit = $page->meta['limit']
            ?? $blueprint->meta['limit']
            ?? config('capell-frontend.pagination_limit', 12);
        $limit = is_numeric($configuredLimit) ? (int) $configuredLimit : 12;
        $configuredOrdering = $blueprint->meta['ordering'] ?? null;
        $ordering = $configuredOrdering instanceof PageOrderEnum
            ? $configuredOrdering
            : (is_string($configuredOrdering) ? PageOrderEnum::tryFrom($configuredOrdering) : null);
        $pageGroup = is_string($blueprint->meta['page_group'] ?? null)
            ? $blueprint->meta['page_group']
            : null;
        $typeKey = is_string($blueprint->meta['page_type'] ?? null)
            ? $blueprint->meta['page_type']
            : null;

        $this->results = PageLoader::list(new PageListingRequestData(
            language: $language,
            site: $site,
            limit: $limit,
            paginationPage: (int) $this->getPage($paginationKey),
            ordering: $ordering ?? PageOrderEnum::Latest,
            pageGroup: $pageGroup,
            typeKey: $typeKey,
            withImage: (bool) ($blueprint->meta['with_image'] ?? false),
            withPagination: (bool) ($blueprint->meta['pagination'] ?? true),
            withParent: (bool) ($blueprint->meta['with_parent'] ?? false),
            withDate: (bool) ($blueprint->meta['with_date'] ?? false),
            paginationKey: 'articles',
            morphModel: Article::class,
        ));

        Frontend::setFrontendData('pagination_results', $this->results);

        $this->latestArticles = PageLoader::list(new PageListingRequestData(
            language: $language,
            site: $site,
            limit: 4,
            ordering: $ordering ?? PageOrderEnum::Latest,
            pageGroup: $pageGroup,
            typeKey: $typeKey,
            withImage: true,
            withPagination: false,
            withParent: false,
            withDate: true,
            morphModel: Article::class,
        ));

        $this->sidebarTags = TagLoader::getTags($site, $language, limit: 12, hasArticles: true);
        $this->tagPage = TagLoader::getTagResultsPage($site, $language);
    }

    /**
     * @return array{
     *     latestArticles: Collection<array-key, mixed>|null,
     *     sidebarTags: Collection<array-key, mixed>|null,
     *     tagPage: Page|null,
     *     blogResultsViewData: BlogResultsViewData
     * }
     */
    #[Override]
    protected function getViewData(): array
    {
        $preparedViewData = Frontend::getFrontendData('blog.results_view_data');

        return [
            'latestArticles' => $this->latestArticles,
            'sidebarTags' => $this->sidebarTags,
            'tagPage' => $this->tagPage,
            'blogResultsViewData' => $preparedViewData instanceof BlogResultsViewData
                ? $preparedViewData
                : BuildBlogResultsViewDataAction::run($this->results),
        ];
    }
}
