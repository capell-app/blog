<?php

declare(strict_types=1);

namespace Capell\Blog\Livewire\Page;

use Capell\Blog\Actions\BuildBlogResultsViewDataAction;
use Capell\Blog\Data\BlogResultsViewData;
use Capell\Blog\Models\Article;
use Capell\Blog\Support\Loader\TagLoader;
use Capell\Core\Enums\PageOrderEnum;
use Capell\Core\Models\Language;
use Capell\Core\Models\Page;
use Capell\Core\Models\Site;
use Capell\Frontend\Facades\Frontend;
use Capell\Frontend\Livewire\Page\AbstractPage;
use Capell\Frontend\Support\Loader\PageLoader;
use Illuminate\Database\Eloquent\Builder;
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

        abort_unless($language instanceof Language && $site instanceof Site, 404);

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

        $paginationPage = config('capell-admin.page_query', 'pageQuery');

        $this->results = PageLoader::getPages(
            language: $language,
            site: $site,
            limit: $page->meta['limit'] ?? $page->blueprint->meta['limit'] ?? config('capell-frontend.pagination_limit', 12),
            paginationPage: (int) $this->getPage($paginationPage),
            ordering: $page->blueprint->meta['ordering'] ?? PageOrderEnum::Latest,
            pageGroup: $page->blueprint->meta['page_group'] ?? null,
            typeKey: $page->blueprint->meta['page_type'] ?? null,
            withImage: $page->blueprint->meta['with_image'] ?? false,
            withPagination: $page->blueprint->meta['pagination'] ?? true,
            withParent: $page->blueprint->meta['with_parent'] ?? false,
            withDate: $page->blueprint->meta['with_date'] ?? false,
            paginationKey: 'articles',
            morphModel: Article::class,
            modifyQuery: function (Builder $query): void {
                $query->with(['tags']);
            },
        );

        Frontend::setFrontendData('pagination_results', $this->results);

        $this->latestArticles = PageLoader::getPages(
            language: $language,
            site: $site,
            limit: 4,
            ordering: $page->blueprint->meta['ordering'] ?? PageOrderEnum::Latest,
            pageGroup: $page->blueprint->meta['page_group'] ?? null,
            typeKey: $page->blueprint->meta['page_type'] ?? null,
            withImage: true,
            withPagination: false,
            withParent: false,
            withDate: true,
            morphModel: Article::class,
            modifyQuery: function (Builder $query): void {
                $query->with(['tags']);
            },
        );

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
