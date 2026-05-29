<?php

declare(strict_types=1);

namespace Capell\Blog\Livewire\Page;

use Capell\Blog\Models\Article;
use Capell\Blog\Support\Loader\TagLoader;
use Capell\Core\Enums\PageOrderEnum;
use Capell\Core\Models\Page;
use Capell\Frontend\Facades\Frontend;
use Capell\Frontend\Livewire\Page\AbstractPage;
use Capell\Frontend\Support\Loader\PageLoader;
use Illuminate\Database\Eloquent\Builder;
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

        $paginationPage = config('capell-admin.page_query', 'pageQuery');

        $this->results = PageLoader::getPages(
            language: $language,
            site: $site,
            limit: $page->meta['limit'] ?? $page->type->meta['limit'] ?? config('capell-frontend.pagination_limit', 12),
            paginationPage: (int) $this->getPage($paginationPage),
            ordering: $page->type->meta['ordering'] ?? PageOrderEnum::Latest,
            pageGroup: $page->type->meta['page_group'] ?? null,
            typeKey: $page->type->meta['page_type'] ?? null,
            withImage: $page->type->meta['with_image'] ?? false,
            withPagination: $page->type->meta['pagination'] ?? true,
            withParent: $page->type->meta['with_parent'] ?? false,
            withDate: $page->type->meta['with_date'] ?? false,
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
            ordering: $page->type->meta['ordering'] ?? PageOrderEnum::Latest,
            pageGroup: $page->type->meta['page_group'] ?? null,
            typeKey: $page->type->meta['page_type'] ?? null,
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
     *     tagPage: Page|null
     * }
     */
    #[Override]
    protected function getViewData(): array
    {
        return [
            'latestArticles' => $this->latestArticles,
            'sidebarTags' => $this->sidebarTags,
            'tagPage' => $this->tagPage,
        ];
    }
}
