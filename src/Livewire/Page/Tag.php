<?php

declare(strict_types=1);

namespace Capell\Blog\Livewire\Page;

use Capell\Blog\Actions\BuildBlogResultsViewDataAction;
use Capell\Blog\Actions\RedirectMergedTagSlugAction;
use Capell\Blog\Data\BlogResultsViewData;
use Capell\Blog\Models\Article;
use Capell\Blog\Support\Loader\TagLoader;
use Capell\Core\Models\Blueprint;
use Capell\Core\Models\Language;
use Capell\Core\Models\Page;
use Capell\Core\Models\Site;
use Capell\Frontend\Data\PageListingRequestData;
use Capell\Frontend\Facades\Frontend;
use Capell\Frontend\Livewire\Page\AbstractPage;
use Capell\Frontend\Support\Loader\PageLoader;
use Capell\Frontend\Support\State\FrontendState;
use Capell\Tags\Models\Tag as TagModel;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Override;

class Tag extends AbstractPage
{
    public ?string $tagSlug = null;

    protected static string $defaultView = 'capell-blog::livewire.page.results';

    protected TagModel $tag;

    protected ?string $tagName = null;

    protected ?BlogResultsViewData $blogResultsViewData = null;

    protected function setup(): void
    {
        $params = Frontend::params();
        $this->tagSlug = $params['tag'] ?? null;

        abort_if(in_array($this->tagSlug, ['', '0', null], true), 404);

        $language = Frontend::language();
        $page = Frontend::page();
        $site = Frontend::site();

        abort_unless($page instanceof Page && $language instanceof Language && $site instanceof Site, 404);

        $blueprint = $page->blueprint;

        abort_unless($blueprint instanceof Blueprint, 404);

        $preparedTag = Frontend::getFrontendData('blog.tag');
        $preparedResults = Frontend::getFrontendData('blog.results');
        $preparedViewData = Frontend::getFrontendData('blog.results_view_data');

        if (
            $preparedTag instanceof TagModel
            && ($preparedResults instanceof Collection || $preparedResults instanceof LengthAwarePaginator)
            && $preparedViewData instanceof BlogResultsViewData
        ) {
            $this->tag = $preparedTag;
            $preparedTagName = Frontend::getFrontendData('blog.tag_name');
            $this->tagName = is_string($preparedTagName) ? $preparedTagName : null;
            $this->results = $preparedResults;
            $this->blogResultsViewData = $preparedViewData;
            $this->params = $this->getReplacementData();

            resolve(FrontendState::class)->withParams($this->params);

            return;
        }

        $resolution = TagLoader::tagPageResolution($this->tagSlug, $site, $language);

        abort_unless($resolution !== null, 404);

        RedirectMergedTagSlugAction::run($resolution, $page, $language);

        $this->tag = $resolution->tag;

        $translatedTagName = $this->tag->getTranslation('name', $language->code);
        $this->tagName = is_string($translatedTagName) ? $translatedTagName : null;

        $configuredPaginationKey = config('capell-admin.page_query', 'pageQuery');
        $paginationKey = is_string($configuredPaginationKey) ? $configuredPaginationKey : 'pageQuery';
        $configuredLimit = $page->meta['limit']
            ?? $blueprint->meta['limit']
            ?? config('capell-frontend.pagination_limit', 12);
        $limit = is_numeric($configuredLimit) ? (int) $configuredLimit : 12;

        $model = Article::class;

        $this->results = PageLoader::list(new PageListingRequestData(
            language: $language,
            site: $site,
            limit: $limit,
            paginationPage: (int) $this->getPage($paginationKey),
            withImage: (bool) ($blueprint->meta['with_image'] ?? true),
            withPagination: (bool) ($blueprint->meta['pagination'] ?? true),
            withDate: (bool) ($blueprint->meta['with_date'] ?? true),
            paginationKey: 'tag-pages',
            cacheKeySuffix: 'tagged-' . $this->tag->id,
            morphModel: $model,
            modifyQuery: function (Builder $query): void {
                $query->whereHas(
                    'tags',
                    fn (Builder $query): Builder => $query->whereKey($this->tag->id),
                );
            },
        ));

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
            'Tag_name' => $this->tagName,
            'tag_slug' => $this->tagSlug,
            'tag_name' => $this->tagName,
            'blogResultsViewData' => $this->blogResultsViewData ?? BuildBlogResultsViewDataAction::run($this->results),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    protected function getReplacementData(): array
    {
        return [
            'Tag_name' => $this->tagName,
            'tag_slug' => $this->tagSlug,
            'tag_name' => $this->tagName,
        ];
    }
}
