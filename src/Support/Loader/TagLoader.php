<?php

declare(strict_types=1);

namespace Capell\Blog\Support\Loader;

use Capell\Blog\Enums\BlogPageTypeEnum;
use Capell\Blog\Enums\CacheEnum;
use Capell\Core\Contracts\Pageable;
use Capell\Core\Facades\CapellCore;
use Capell\Core\Models\Language;
use Capell\Core\Models\Page;
use Capell\Core\Models\Site;
use Capell\Frontend\Contracts\RenderedModelTracker;
use Capell\Tags\Enums\TagTypeEnum;
use Capell\Tags\Models\Tag;
use Illuminate\Contracts\Database\Eloquent\Builder as BuilderContract;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Cache;

class TagLoader
{
    public static function getPageTags(Pageable $page): Collection
    {
        $key = CacheEnum::pageTags($page->id);

        $fromCache = true;

        $tags = CapellCore::rememberCache($key, function () use ($page, &$fromCache): Collection {
            $fromCache = false;

            return $page->tags()->ordered()->get();
        });

        if ($fromCache) {
            $tags->each(function (Tag $tag): void {
                resolve(RenderedModelTracker::class)->track($tag);
            });
        }

        return $tags;
    }

    public static function getTagResultsPage(Site $site, Language $language): ?Page
    {
        $cacheKey = CacheEnum::tagResultsPage($site->id, $language->id);

        $fromCache = true;

        $page = CapellCore::rememberCache($cacheKey, function () use ($site, $language, &$fromCache): ?Page {
            $fromCache = false;

            /** @var class-string<Page> $model */
            $model = Page::class;

            return $model::getFirstPageByTypeForSite(BlogPageTypeEnum::Tag->value, site: $site, language: $language);
        });

        if ($fromCache && $page instanceof Pageable) {
            resolve(RenderedModelTracker::class)->track($page);
        }

        return $page;
    }

    /**
     * Returns a query builder for tags for use in chunked/large operations.
     */
    public static function getTagsQuery(
        Site $site,
        Language $language,
        bool $hasArticles = false,
    ): Builder {
        /* @var class-string<Tag> $model */
        $model = Tag::class;

        return $model::query()
            ->withCount([
                'taggables' => fn (Builder $query): Builder => $query->whereHas(
                    'taggable',
                    fn (Builder $query): Builder => $query->where('site_id', $site->id)
                        ->whereRelation('translation', 'language_id', $language->id),
                ),
            ])
            ->where('type', TagTypeEnum::Page)
            ->where(
                fn (Builder $query): Builder => $query->where('site_id', $site->id)->orWhereNull('site_id'),
            )
            ->when(
                $hasArticles,
                fn (Builder $query) => $query->whereHas(
                    'taggables',
                    fn (BuilderContract $query): BuilderContract => $query->whereHas(
                        'taggable',
                        fn (BuilderContract $query): BuilderContract => $query->where('site_id', $site->id)
                            ->whereRelation('translation', 'language_id', $language->id),
                    ),
                ),
            )
            ->tap(fn (Builder $query) => $query->whereNotNull($query->qualifyColumn('name->' . $language->code)))
            ->ordered();
    }

    /**
     * Returns a collection or paginator of tags (cached, for UI use).
     */
    public static function getTags(
        Site $site,
        Language $language,
        ?int $limit = null,
        bool $hasArticles = false,
        ?int $paginationPage = null,
        bool $withPagination = false,
        string $paginationKey = 'page',
    ): Collection|LengthAwarePaginator {
        if ($withPagination && ($limit === null || $limit === 0)) {
            $limit = config('capell-frontend.pagination_limit', 10);
        }

        $paginationPage = $withPagination ? max(1, $paginationPage ?? 1) : $paginationPage;
        $version = Cache::store()->get(CacheEnum::siteTagsVersion($site->id, $language->id), 0);
        $version = is_numeric($version) ? (int) $version : 0;

        $cacheKey = CacheEnum::siteTags($site->id, $language->id, $hasArticles, $limit, $paginationPage, $paginationKey, $version);

        $fromCache = true;

        $tags = CapellCore::rememberCache($cacheKey, function () use (
            $language,
            $hasArticles,
            $limit,
            $paginationPage,
            $paginationKey,
            $site,
            $withPagination,
            &$fromCache
        ) {
            $fromCache = false;
            $query = self::getTagsQuery($site, $language, $hasArticles);
            if ($withPagination) {
                return $query->paginate($limit, ['*'], $paginationKey, $paginationPage);
            }

            if ($limit !== null) {
                $query->limit($limit);
            }

            return $query->get();
        });

        if ($fromCache) {
            self::trackCachedTags($tags);
        }

        if ($tags instanceof LengthAwarePaginator) {
            $tags->withPath(request()->url());
        }

        return $tags;
    }

    public static function tagPage(string $slug, Site $site, Language $language): ?Tag
    {
        $key = CacheEnum::tagPage($site->id, $language->id, $slug);

        $fromCache = true;

        $tag = CapellCore::rememberCache($key, function () use ($slug, $site, $language, &$fromCache): ?Tag {
            $fromCache = false;

            /** @var class-string<Tag> $model */
            $model = Tag::class;

            return $model::query()->where('type', TagTypeEnum::Page->value)
                ->where('slug->' . $language->code, $slug)
                ->where(
                    fn (Builder $query): Builder => $query->where('site_id', $site->id)->orWhereNull('site_id'),
                )
                ->orderByRaw('CASE WHEN site_id = ? THEN 0 ELSE 1 END', [$site->id])
                ->first();
        });

        if ($fromCache && $tag instanceof Tag) {
            resolve(RenderedModelTracker::class)->track($tag);
        }

        return $tag;
    }

    private static function trackCachedTags(Collection|LengthAwarePaginator $tags): void
    {
        $collection = $tags instanceof LengthAwarePaginator ? $tags->getCollection() : $tags;

        $collection->each(function (Tag $tag): void {
            resolve(RenderedModelTracker::class)->track($tag);
        });
    }
}
