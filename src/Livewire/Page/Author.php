<?php

declare(strict_types=1);

namespace Capell\Blog\Livewire\Page;

use Capell\Blog\Actions\BuildBlogResultsViewDataAction;
use Capell\Blog\Actions\ResolveBlogAuthorBySlugAction;
use Capell\Blog\Data\BlogAuthorData;
use Capell\Blog\Data\BlogResultsViewData;
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
use Override;

class Author extends AbstractPage
{
    public ?string $authorSlug = null;

    protected static string $defaultView = 'capell-blog::livewire.page.results';

    protected ?string $authorName = null;

    protected ?BlogResultsViewData $blogResultsViewData = null;

    protected function setup(): void
    {
        $params = Frontend::params();
        $requestedSlug = $params['author'] ?? null;

        abort_unless(is_string($requestedSlug) && $requestedSlug !== '', 404);

        $language = Frontend::language();
        $page = Frontend::page();
        $site = Frontend::site();

        abort_unless($page instanceof Page && $language instanceof Language && $site instanceof Site, 404);

        $blueprint = $page->blueprint;

        abort_unless($blueprint instanceof Blueprint, 404);

        $author = ResolveBlogAuthorBySlugAction::run($requestedSlug, $site, $language);

        abort_unless($author instanceof BlogAuthorData, 404);

        $this->authorSlug = $author->slug;
        $this->authorName = $author->name;

        $configuredPaginationKey = config('capell-admin.page_query', 'pageQuery');
        $paginationKey = is_string($configuredPaginationKey) ? $configuredPaginationKey : 'pageQuery';
        $configuredLimit = $page->meta['limit']
            ?? $blueprint->meta['limit']
            ?? config('capell-frontend.pagination_limit', 12);
        $limit = is_numeric($configuredLimit) ? (int) $configuredLimit : 12;

        $requestedPage = $this->getPage($paginationKey);
        $paginationPage = is_numeric($requestedPage) ? max(1, (int) $requestedPage) : 1;

        $article = new Article;
        $createdByColumn = $article->qualifyColumn($article->getCreatedByColumn());
        $authorId = $author->userId;

        $this->results = PageLoader::list(new PageListingRequestData(
            language: $language,
            site: $site,
            limit: $limit,
            paginationPage: $paginationPage,
            withImage: (bool) ($blueprint->meta['with_image'] ?? true),
            withPagination: (bool) ($blueprint->meta['pagination'] ?? true),
            withDate: (bool) ($blueprint->meta['with_date'] ?? true),
            paginationKey: 'author-pages',
            cacheKeySuffix: 'authored-' . $authorId,
            morphModel: Article::class,
            modifyQuery: function (Builder $query) use ($createdByColumn, $authorId): void {
                $query->where($createdByColumn, $authorId);
            },
        ));

        abort_if($this->results->isEmpty(), 404);

        $this->blogResultsViewData = BuildBlogResultsViewDataAction::run($this->results);
        $this->params = $this->getReplacementData();

        resolve(FrontendState::class)->withParams($this->params);
    }

    /**
     * @return array<string, mixed>
     */
    #[Override]
    protected function getViewData(): array
    {
        return [
            ...$this->getReplacementData(),
            'blogResultsViewData' => $this->blogResultsViewData ?? BuildBlogResultsViewDataAction::run($this->results),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    protected function getReplacementData(): array
    {
        return [
            'Author_name' => $this->authorName,
            'author_name' => $this->authorName,
            'author_slug' => $this->authorSlug,
        ];
    }
}
