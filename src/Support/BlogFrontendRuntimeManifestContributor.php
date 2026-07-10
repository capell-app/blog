<?php

declare(strict_types=1);

namespace Capell\Blog\Support;

use Capell\Blog\Actions\BuildArticleMetaDataAction;
use Capell\Blog\Actions\BuildBlogResultsViewDataAction;
use Capell\Blog\Actions\RedirectMergedTagSlugAction;
use Capell\Blog\Data\ArticleMetaData;
use Capell\Blog\Data\ArticleNeighborLinkData;
use Capell\Blog\Data\ArticleWidgetRenderData;
use Capell\Blog\Enums\BlogLayoutEnum;
use Capell\Blog\Enums\BlogPageTypeEnum;
use Capell\Blog\Enums\BlogTypeGroupEnum;
use Capell\Blog\Enums\ResourceEnum;
use Capell\Blog\Models\Article;
use Capell\Blog\Support\Loader\BlogLoader;
use Capell\Blog\Support\Loader\TagLoader;
use Capell\Core\Contracts\Pageable;
use Capell\Core\Enums\BlueprintGroupEnum;
use Capell\Core\Enums\PageOrderEnum;
use Capell\Core\Models\Language;
use Capell\Core\Models\Page;
use Capell\Core\Models\Site;
use Capell\FoundationTheme\Actions\ResolveFoundationThemeTokensAction;
use Capell\Frontend\Contracts\FrontendContextReader;
use Capell\Frontend\Contracts\FrontendRuntimeManifestContributor;
use Capell\Frontend\Data\FrontendRuntimeManifestData;
use Capell\Frontend\Support\Loader\PageLoader;
use Capell\Frontend\Support\Loader\SiteLoader;
use Capell\Navigation\Models\Navigation;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

final class BlogFrontendRuntimeManifestContributor implements FrontendRuntimeManifestContributor
{
    public function contribute(FrontendContextReader $context, FrontendRuntimeManifestData $manifest): void
    {
        $page = $context->page();
        $site = $context->site();
        $language = $context->language();

        if (! $page instanceof Pageable || ! $page instanceof Model || ! $site instanceof Site || ! $language instanceof Language) {
            return;
        }

        match ($this->pageTypeKey($page)) {
            BlogPageTypeEnum::Archive->value => $this->prepareArchivePage($context, $page, $site, $language),
            BlogPageTypeEnum::Article->value => $this->prepareArticlePage($context, $page, $site, $language),
            BlogPageTypeEnum::Blog->value => $this->prepareBlogPage($context, $page, $site, $language),
            BlogPageTypeEnum::Tag->value => $this->prepareTagPage($context, $page, $site, $language),
            default => null,
        };

        match ($this->layoutKey($page)) {
            BlogLayoutEnum::Archives->value => $this->prepareArchivesIndexPage($context, $page, $site, $language),
            BlogLayoutEnum::Tags->value => $this->prepareTagsIndexPage($context, $page, $site, $language),
            default => null,
        };

        $this->prepareFooterWidgetData($context, $site, $language);
    }

    /**
     * Prepares the data `Capell\Blog\View\Components\Footer\Tags` and
     * `Footer\Pages` fall back to querying live otherwise.
     *
     * `FooterTagsRenderHook` / `FooterPagesRenderHook` are registered
     * globally against `RenderHookLocation::Footer` (see
     * `FrontendServiceProvider::registerRenderHooks()`) and render on every
     * page's footer, not just blog page types -- unlike the page-type-gated
     * `prepare*Page()` methods above, this must run unconditionally so a
     * non-blog page (any theme reusing Foundation's default footer) doesn't
     * fall through to a live, uncached query during the guarded Blade
     * render. Mirrors `FoundationThemeServiceProvider::prepareFooterData()`'s
     * same "already prepared by a more specific step" guard.
     */
    private function prepareFooterWidgetData(FrontendContextReader $context, Site $site, Language $language): void
    {
        if ($context->getFrontendData('blog.sidebar_tags') !== null) {
            return;
        }

        $context->setFrontendData('blog.sidebar_tags', TagLoader::getTags($site, $language, limit: 5, hasArticles: true));
        $context->setFrontendData('blog.tag_page', TagLoader::getTagResultsPage($site, $language));
        $context->setFrontendData('blog.latest_articles', PageLoader::getPages(
            language: $language,
            site: $site,
            limit: 4,
            ordering: PageOrderEnum::Latest,
            pageGroup: BlogTypeGroupEnum::Article,
            withImage: true,
            morphModel: Article::class,
        ));
    }

    private function prepareArchivePage(FrontendContextReader $context, Pageable&Model $page, Site $site, Language $language): void
    {
        $this->prepareFoundationThemeRuntimeData($context, $page, $site, $language);
        $this->prepareArchiveSidebarData($context, $site, $language);
        $pageMeta = $this->modelMeta($page);
        $typeMeta = $this->modelMeta($this->loadedModel($page, 'type'));

        $archiveDate = $this->archiveDateFromParams($context);

        if ($archiveDate === null) {
            return;
        }

        $requestedPage = request()->query($this->pageQueryKey(), 1);

        $results = PageLoader::getPages(
            language: $language,
            site: $site,
            limit: $this->metaInt($pageMeta, 'limit') ?? $this->metaInt($typeMeta, 'limit') ?? $this->paginationLimit(),
            paginationPage: $this->requestedPage($requestedPage),
            typeKey: $this->metaString($typeMeta, 'page_group') ?? strtolower(ResourceEnum::Article->name),
            withImage: $this->metaBool($typeMeta, 'with_image', false),
            withPagination: $this->metaBool($typeMeta, 'pagination', true),
            withDate: $this->metaBool($typeMeta, 'with_date', false),
            paginationKey: 'article-archives',
            cacheKeyPrepend: sprintf('year-%s-month-%s', $archiveDate['year'], $archiveDate['month']),
            morphModel: Article::class,
            modifyQuery: function (Builder $query) use ($archiveDate): void {
                $query->with(['tags']);

                $this->filterArchiveQuery($query, $archiveDate['year'], $archiveDate['month']);
            },
        );

        $context->setFrontendData('blog.results', $results);
        $context->setFrontendData('blog.results_view_data', BuildBlogResultsViewDataAction::run($results));
    }

    private function prepareArticlePage(FrontendContextReader $context, Pageable&Model $page, Site $site, Language $language): void
    {
        $site->loadMissing('siteDomain');
        $this->hydrateSiteNavigations($site);
        $page->loadMissing(['creator', 'image.translations.language', 'pageUrl', 'tags', 'translation', 'type']);
        $creator = $page->getRelation('creator');

        if ($creator instanceof Model && method_exists($creator, 'profileImage')) {
            $creator->loadMissing('profileImage.translations.language');
        }

        $this->prepareFoundationThemeRuntimeData($context, $page, $site, $language);

        $articleMeta = BuildArticleMetaDataAction::run(
            page: $page,
            site: $site,
            language: $language,
            withAuthor: true,
        );

        if (! $articleMeta instanceof ArticleMetaData) {
            return;
        }

        $context->setFrontendData('blog.article.meta', $articleMeta);
        $context->setFrontendData('blog.article.render_data', $this->articleRenderData($page, $site, $language, $articleMeta));
        $latestArticles = PageLoader::getPages(
            language: $language,
            site: $site,
            limit: 3,
            ordering: PageOrderEnum::Latest,
            pageGroup: BlogTypeGroupEnum::Article,
            withImage: true,
            morphModel: Article::class,
        );
        $this->loadPageMediaTranslations($latestArticles);
        $context->setFrontendData('blog.latest_articles', $latestArticles);
        $context->setFrontendData('blog.sidebar_tags', TagLoader::getTags($site, $language, limit: 5, hasArticles: true));
        $context->setFrontendData('blog.tag_page', TagLoader::getTagResultsPage($site, $language));
        $this->prepareArchiveWidget($context, $site, $language);
        $this->prepareRelatedArticles($context, $page, $site, $language, $articleMeta);
    }

    private function prepareBlogPage(FrontendContextReader $context, Pageable&Model $page, Site $site, Language $language): void
    {
        $this->prepareFoundationThemeRuntimeData($context, $page, $site, $language);
        $pageMeta = $this->modelMeta($page);
        $typeMeta = $this->modelMeta($this->loadedModel($page, 'type'));

        $requestedPage = request()->query($this->pageQueryKey(), 1);

        $results = PageLoader::getPages(
            language: $language,
            site: $site,
            limit: $this->metaInt($pageMeta, 'limit') ?? $this->metaInt($typeMeta, 'limit') ?? $this->paginationLimit(),
            paginationPage: $this->requestedPage($requestedPage),
            ordering: $this->metaPageOrder($typeMeta, 'ordering') ?? PageOrderEnum::Latest,
            pageGroup: $this->metaString($typeMeta, 'page_group'),
            typeKey: $this->metaString($typeMeta, 'page_type'),
            withImage: $this->metaBool($typeMeta, 'with_image', false),
            withPagination: $this->metaBool($typeMeta, 'pagination', true),
            withParent: $this->metaBool($typeMeta, 'with_parent', false),
            withDate: $this->metaBool($typeMeta, 'with_date', false),
            paginationKey: 'articles',
            morphModel: Article::class,
            modifyQuery: function (Builder $query): void {
                $query->with(['tags']);
            },
        );
        $this->loadPageMediaTranslations($results);

        $context->setFrontendData('blog.results', $results);
        $context->setFrontendData('pagination_results', $results);
        $latestArticles = PageLoader::getPages(
            language: $language,
            site: $site,
            limit: 4,
            ordering: $this->metaPageOrder($typeMeta, 'ordering') ?? PageOrderEnum::Latest,
            pageGroup: $this->metaString($typeMeta, 'page_group'),
            typeKey: $this->metaString($typeMeta, 'page_type'),
            withImage: true,
            withPagination: false,
            withParent: false,
            withDate: true,
            morphModel: Article::class,
            modifyQuery: function (Builder $query): void {
                $query->with(['tags']);
            },
        );
        $this->loadPageMediaTranslations($latestArticles);
        $context->setFrontendData('blog.latest_articles', $latestArticles);
        $context->setFrontendData('blog.sidebar_tags', TagLoader::getTags($site, $language, limit: 12, hasArticles: true));
        $context->setFrontendData('blog.tag_page', TagLoader::getTagResultsPage($site, $language));
        $context->setFrontendData('blog.results_view_data', BuildBlogResultsViewDataAction::run($results));
        $this->prepareArchiveWidget($context, $site, $language);
    }

    private function prepareTagPage(FrontendContextReader $context, Pageable&Model $page, Site $site, Language $language): void
    {
        $this->prepareFoundationThemeRuntimeData($context, $page, $site, $language);
        $pageMeta = $this->modelMeta($page);
        $typeMeta = $this->modelMeta($this->loadedModel($page, 'type'));

        $tagSlug = $context->params()['tag'] ?? null;

        if (! is_string($tagSlug) || $tagSlug === '') {
            return;
        }

        $resolution = TagLoader::tagPageResolution($tagSlug, $site, $language);

        if ($resolution === null) {
            return;
        }

        abort_unless($page instanceof Page, 404);

        RedirectMergedTagSlugAction::run($resolution, $page, $language);

        $tag = $resolution->tag;

        $requestedPage = request()->query($this->pageQueryKey(), 1);

        $results = PageLoader::getPages(
            language: $language,
            site: $site,
            limit: $this->metaInt($pageMeta, 'limit') ?? $this->metaInt($typeMeta, 'limit') ?? $this->paginationLimit(),
            paginationPage: $this->requestedPage($requestedPage),
            withImage: $this->metaBool($typeMeta, 'with_image', true),
            withPagination: $this->metaBool($typeMeta, 'pagination', true),
            withDate: $this->metaBool($typeMeta, 'with_date', true),
            paginationKey: 'tag-pages',
            cacheKeyPrepend: 'tagged-' . $tag->id,
            morphModel: Article::class,
            modifyQuery: function (Builder $query) use ($tag): void {
                $query->with(['tags'])->whereHas(
                    'tags',
                    fn (Builder $query): Builder => $query->whereKey($tag->id),
                );
            },
        );
        $this->loadPageMediaTranslations($results);

        $context->setFrontendData('blog.tag', $tag);
        $context->setFrontendData('blog.tag_name', $tag->getTranslation('name', $language->code));
        $context->setFrontendData('blog.results', $results);
        $context->setFrontendData('blog.results_view_data', BuildBlogResultsViewDataAction::run($results));
        $this->prepareArchiveWidget($context, $site, $language);
    }

    private function prepareTagsIndexPage(FrontendContextReader $context, Pageable&Model $page, Site $site, Language $language): void
    {
        $site->loadMissing('siteDomain');
        $this->hydrateSiteNavigations($site);
        $page->loadMissing(['translation', 'pageUrl', 'type']);

        $this->prepareFoundationThemeRuntimeData($context, $page, $site, $language);

        $latestArticles = PageLoader::getPages(
            language: $language,
            site: $site,
            limit: 4,
            ordering: PageOrderEnum::Latest,
            withImage: true,
            morphModel: Article::class,
        );
        $this->loadPageMediaTranslations($latestArticles);
        $context->setFrontendData('blog.latest_articles', $latestArticles);
        $context->setFrontendData('blog.sidebar_tags', TagLoader::getTags($site, $language, hasArticles: true));
        $context->setFrontendData('blog.tag_page', TagLoader::getTagResultsPage($site, $language));
    }

    private function prepareArchivesIndexPage(FrontendContextReader $context, Pageable&Model $page, Site $site, Language $language): void
    {
        $site->loadMissing('siteDomain');
        $this->hydrateSiteNavigations($site);
        $page->loadMissing(['translation', 'pageUrl', 'type']);

        $this->prepareFoundationThemeRuntimeData($context, $page, $site, $language);
        $this->prepareArchiveSidebarData($context, $site, $language);
        $this->prepareArchiveWidget($context, $site, $language);
    }

    private function prepareArchiveSidebarData(FrontendContextReader $context, Site $site, Language $language): void
    {
        $latestArticles = PageLoader::getPages(
            language: $language,
            site: $site,
            limit: 4,
            ordering: PageOrderEnum::Latest,
            withImage: true,
            morphModel: Article::class,
        );
        $this->loadPageMediaTranslations($latestArticles);
        $context->setFrontendData('blog.latest_articles', $latestArticles);
        $context->setFrontendData('blog.sidebar_tags', TagLoader::getTags($site, $language, limit: 5, hasArticles: true));
        $context->setFrontendData('blog.tag_page', TagLoader::getTagResultsPage($site, $language));
    }

    private function prepareArchiveWidget(FrontendContextReader $context, Site $site, Language $language): void
    {
        $archivePage = BlogLoader::getArchivePage($site, $language);

        if ($archivePage instanceof Model) {
            $archivePage->loadMissing('pageUrl');
        }

        $context->setFrontendData('blog.archive_page', $archivePage);
        $context->setFrontendData('blog.archives', BlogLoader::getArchives(
            site: $site,
            language: $language,
            group: BlogTypeGroupEnum::Article->value,
            limit: $this->paginationLimit(),
        ));
    }

    private function prepareFoundationThemeRuntimeData(FrontendContextReader $context, Pageable&Model $page, Site $site, Language $language): void
    {
        $context->setFrontendData('foundation.theme.tokens', ResolveFoundationThemeTokensAction::run(
            theme: $context->theme(),
            site: $site,
        ));
        $context->setFrontendData('foundation.footer.contact_page', Page::getFirstPageByTypeForSite('contact', $site, $language));
        $context->setFrontendData('foundation.footer.site_languages', SiteLoader::pageLanguages($site, $language, $page));
        $context->setFrontendData('foundation.footer.latest_pages', PageLoader::getPages(
            language: $language,
            site: $site,
            limit: 4,
            ordering: PageOrderEnum::Latest,
            pageGroup: BlueprintGroupEnum::Default,
        ));
        $context->setFrontendData('foundation.footer.related_sites', SiteLoader::related($site, $language)
            ->map(function (Site $relatedSite): array {
                $relations = $relatedSite->getRelations();
                $siteDomain = $relations['siteDomain'] ?? null;
                $translation = $relations['translation'] ?? null;

                return [
                    'description' => data_get($translation, 'meta.description'),
                    'primaryColor' => $relatedSite->getThemeColor('primary'),
                    'title' => data_get($translation, 'title'),
                    'url' => data_get($siteDomain, 'full_url'),
                ];
            })
            ->filter(fn (array $relatedSite): bool => is_string($relatedSite['url']) && $relatedSite['url'] !== '')
            ->values());
        $context->setFrontendData('foundation.page.ancestors', PageLoader::getPageAncestors($page, $language, $site));
        $context->setFrontendData('foundation.page.home', $site->getHomePage($language));
    }

    private function prepareRelatedArticles(
        FrontendContextReader $context,
        Pageable&Model $page,
        Site $site,
        Language $language,
        ArticleMetaData $articleMeta,
    ): void {
        $tagIds = $articleMeta->tags
            ->pluck('id')
            ->filter(fn (mixed $tagId): bool => is_int($tagId) || is_string($tagId))
            ->map(fn (int|string $tagId): string => (string) $tagId)
            ->values()
            ->all();

        $relatedArticles = PageLoader::getPages(
            language: $language,
            site: $site,
            limit: $this->paginationLimit(),
            withImage: true,
            withDate: true,
            cacheKeyPrepend: 'tags-' . implode('-', $tagIds),
            morphModel: Article::class,
            modifyQuery: function (Builder $query) use ($page, $tagIds): void {
                $idColumn = $query->getModel()->qualifyColumn('id');

                $query->where($idColumn, '!=', $page->getKey())
                    ->when(
                        $tagIds !== [],
                        fn (Builder $query): Builder => $query->whereHas(
                            'tags',
                            fn (Builder $query): Builder => $query->whereIn('taggables.tag_id', $tagIds),
                        ),
                    );
            },
        );
        $this->loadPageMediaTranslations($relatedArticles);
        $context->setFrontendData('blog.related_articles', $relatedArticles);
    }

    private function loadPageMediaTranslations(mixed $pages): void
    {
        if ($pages instanceof EloquentCollection) {
            $pages->loadMissing('image.translations.language');

            return;
        }

        if (is_object($pages) && method_exists($pages, 'getCollection')) {
            $collection = $pages->getCollection();

            if ($collection instanceof EloquentCollection) {
                $collection->loadMissing('image.translations.language');
            }
        }
    }

    /**
     * @return array{year: int, month: int|null}|null
     */
    private function archiveDateFromParams(FrontendContextReader $context): ?array
    {
        $date = $context->params()['date'] ?? '';

        if (! is_string($date) || $date === '' || $date === '0') {
            return null;
        }

        $parts = explode('/', $date);
        $dateParts = explode('-', $parts[0]);
        $year = isset($dateParts[0]) && mb_strlen($dateParts[0]) === 4 && is_numeric($dateParts[0])
            ? (int) $dateParts[0]
            : null;
        $month = isset($dateParts[1]) && is_numeric($dateParts[1]) && (int) $dateParts[1] >= 1 && (int) $dateParts[1] <= 12
            ? (int) $dateParts[1]
            : null;

        if ($year === null || $year === 0) {
            return null;
        }

        return ['year' => $year, 'month' => $month];
    }

    /**
     * @param  Builder<Model>  $query
     * @return Builder<Model>
     */
    private function filterArchiveQuery(Builder $query, int $year, ?int $month): Builder
    {
        if (DB::getDriverName() === 'sqlite') {
            $query
                ->whereRaw("strftime('%Y', COALESCE(`visible_from`, `created_at`)) = ?", [(string) $year])
                ->when(
                    $month,
                    fn (Builder $query): Builder => $query->whereRaw(
                        "strftime('%m', COALESCE(`visible_from`, `created_at`)) = ?",
                        [str_pad((string) $month, 2, '0', STR_PAD_LEFT)],
                    ),
                );

            return $query;
        }

        return $query
            ->whereRaw('YEAR(COALESCE(`visible_from`, `created_at`)) = ?', [$year])
            ->when(
                $month,
                fn (Builder $query): Builder => $query->whereRaw(
                    'MONTH(COALESCE(`visible_from`, `created_at`)) = ?',
                    [$month],
                ),
            );
    }

    private function hydrateSiteNavigations(Site $site): void
    {
        if ($site->relationLoaded('navigations') || ! class_exists(Navigation::class)) {
            return;
        }

        $site->setRelation(
            'navigations',
            Navigation::query()
                ->where(fn (Builder $query): Builder => $query
                    ->where('site_id', $site->getKey())
                    ->orWhereNull('site_id'))
                ->publishedDate()
                ->get(),
        );
    }

    private function articleRenderData(Pageable&Model $page, Site $site, Language $language, ArticleMetaData $articleMeta): ArticleWidgetRenderData
    {
        $pageTranslation = ArticleWidgetRenderData::loadedRelation($page, 'translation');
        $pageType = ArticleWidgetRenderData::loadedRelation($page, 'type');
        $siteDomain = $this->loadedModel($site, 'siteDomain');
        $articleImage = ArticleWidgetRenderData::loadedRelation($page, 'image');
        $pageTypeMeta = $pageType instanceof Model && is_array($pageType->getAttribute('meta'))
            ? $pageType->getAttribute('meta')
            : [];

        $previousPage = null;
        $nextPage = null;

        if (! isset($pageTypeMeta['hidden']) && (bool) $page->getMeta('with_next_prev')) {
            $previousPage = PageLoader::getPreviousPage($page, $site, $language);
            $nextPage = PageLoader::getNextPage($page, $site, $language);
        }

        return new ArticleWidgetRenderData(
            title: is_string($pageTranslation?->getAttribute('title')) ? $pageTranslation->getAttribute('title') : null,
            label: is_string($pageTranslation?->getAttribute('label')) ? $pageTranslation->getAttribute('label') : null,
            summary: is_string($pageTranslation?->getAttribute('summary')) ? $pageTranslation->getAttribute('summary') : null,
            content: is_string($pageTranslation?->getAttribute('content')) ? $pageTranslation->getAttribute('content') : null,
            contentStructure: $pageType?->getAttribute('content_structure'),
            image: $articleImage,
            authorProfileImage: $articleMeta->author instanceof Model
                ? ArticleWidgetRenderData::loadedRelation($articleMeta->author, 'profileImage')
                : null,
            publishedDate: $page->getAttribute('visible_from') ?: $page->getAttribute('created_at'),
            blogUrl: BlogLoader::getBlogPageUrl($site, $language),
            homeUrl: is_string($siteDomain?->getAttribute('url')) ? $siteDomain->getAttribute('url') : null,
            previous: ArticleNeighborLinkData::fromPage($previousPage),
            next: ArticleNeighborLinkData::fromPage($nextPage),
        );
    }

    private function pageTypeKey(Model $page): ?string
    {
        $type = $page->relationLoaded('type') ? $page->getRelation('type') : null;
        $key = $type instanceof Model ? $type->getAttribute('key') : null;

        return is_string($key) ? $key : null;
    }

    private function loadedModel(Model $model, string $relation): ?Model
    {
        $related = $model->relationLoaded($relation) ? $model->getRelation($relation) : null;

        return $related instanceof Model ? $related : null;
    }

    private function pageQueryKey(): string
    {
        $key = config('capell-admin.page_query', 'pageQuery');

        return is_string($key) && $key !== '' ? $key : 'pageQuery';
    }

    private function paginationLimit(): int
    {
        $limit = config('capell-frontend.pagination_limit', 12);

        return is_int($limit) ? $limit : 12;
    }

    private function requestedPage(mixed $requestedPage): int
    {
        if (is_int($requestedPage)) {
            return max(1, $requestedPage);
        }

        if (is_string($requestedPage) && ctype_digit($requestedPage)) {
            return max(1, (int) $requestedPage);
        }

        return 1;
    }

    /**
     * @return array<string, mixed>
     */
    private function modelMeta(?Model $model): array
    {
        $meta = $model?->getAttribute('meta');

        return is_array($meta) ? $meta : [];
    }

    /**
     * @param  array<string, mixed>  $meta
     */
    private function metaBool(array $meta, string $key, bool $default): bool
    {
        $value = $meta[$key] ?? null;

        return is_bool($value) ? $value : $default;
    }

    /**
     * @param  array<string, mixed>  $meta
     */
    private function metaInt(array $meta, string $key): ?int
    {
        $value = $meta[$key] ?? null;

        return is_int($value) ? $value : null;
    }

    /**
     * @param  array<string, mixed>  $meta
     */
    private function metaPageOrder(array $meta, string $key): ?PageOrderEnum
    {
        $value = $meta[$key] ?? null;

        if ($value instanceof PageOrderEnum) {
            return $value;
        }

        return is_string($value) ? PageOrderEnum::tryFrom($value) : null;
    }

    /**
     * @param  array<string, mixed>  $meta
     */
    private function metaString(array $meta, string $key): ?string
    {
        $value = $meta[$key] ?? null;

        return is_string($value) ? $value : null;
    }

    private function layoutKey(Model $page): ?string
    {
        $page->loadMissing('layout');

        $layout = $page->getRelation('layout');
        $key = $layout instanceof Model ? $layout->getAttribute('key') : null;

        return is_string($key) ? $key : null;
    }
}
