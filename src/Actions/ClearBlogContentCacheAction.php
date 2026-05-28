<?php

declare(strict_types=1);

namespace Capell\Blog\Actions;

use Capell\Blog\Enums\BlogPageTypeEnum;
use Capell\Blog\Enums\CacheEnum;
use Capell\Blog\Models\Article;
use Capell\Core\Facades\CapellCore;
use Capell\Core\Models\Language;
use Capell\Core\Models\Site;
use Capell\Frontend\Enums\CacheEnum as FrontendCacheEnum;
use Capell\Frontend\Support\Cache\PublicPageRenderDataCache;
use Illuminate\Support\Facades\Cache;
use Lorisleiva\Actions\Concerns\AsObject;

final class ClearBlogContentCacheAction
{
    use AsObject;

    public function handle(Article $article): void
    {
        if (is_numeric($article->getKey())) {
            CapellCore::removeCacheKey(CacheEnum::pageTags((int) $article->getKey()));
        }

        $siteIds = collect([$article->site_id, $article->getOriginal('site_id')])
            ->filter(fn (mixed $siteId): bool => is_numeric($siteId))
            ->map(fn (mixed $siteId): int => (int) $siteId)
            ->unique();

        foreach ($siteIds as $siteId) {
            $site = Site::query()
                ->with(['language', 'siteDomains.language'])
                ->find($siteId);

            if (! $site instanceof Site) {
                continue;
            }

            $languageIds = $site->getAllLanguages()
                ->map(fn (Language $language): int => (int) $language->getKey())
                ->unique();

            foreach ($languageIds as $languageId) {
                $this->clearSiteLanguageCache($siteId, $languageId);

                if (is_numeric($article->getKey())) {
                    $this->clearArticlePageCache($article, $siteId, $languageId);
                }
            }

            $this->clearSiteLanguageAgnosticCache($siteId);
        }
    }

    private function clearArticlePageCache(Article $article, int $siteId, int $languageId): void
    {
        $articleId = (int) $article->getKey();

        foreach ([$article::class, $article->getMorphClass()] as $pageType) {
            CapellCore::removeCacheKey(FrontendCacheEnum::pageModel($pageType, $articleId, $siteId, $languageId));
        }

        resolve(PublicPageRenderDataCache::class)->invalidate($article::class, $articleId, $siteId, $languageId);
    }

    private function clearSiteLanguageCache(int $siteId, int $languageId): void
    {
        foreach ([
            BlogPageTypeEnum::Blog,
            BlogPageTypeEnum::Archive,
            BlogPageTypeEnum::Tag,
        ] as $pageType) {
            CapellCore::removeCacheKey(CacheEnum::blogPage($siteId, $languageId, $pageType->value));
        }

        CapellCore::removeCacheKey(CacheEnum::archivePage($siteId, $languageId));
        CapellCore::removeCacheKey(CacheEnum::tagResultsPage($siteId, $languageId));
        $this->incrementArchivesVersion($siteId, $languageId);
        $this->incrementSiteTagsVersion($siteId, $languageId);
    }

    private function clearSiteLanguageAgnosticCache(int $siteId): void
    {
        foreach ([
            BlogPageTypeEnum::Blog,
            BlogPageTypeEnum::Archive,
            BlogPageTypeEnum::Tag,
        ] as $pageType) {
            CapellCore::removeCacheKey(CacheEnum::blogPage($siteId, 'null', $pageType->value));
        }
    }

    private function incrementSiteTagsVersion(int $siteId, int $languageId): void
    {
        $key = CacheEnum::siteTagsVersion($siteId, $languageId);
        $this->incrementCacheVersion($key);
    }

    private function incrementArchivesVersion(int $siteId, int $languageId): void
    {
        $key = CacheEnum::archivesVersion($siteId, $languageId);
        $this->incrementCacheVersion($key);
    }

    private function incrementCacheVersion(string $key): void
    {
        $cache = Cache::store();
        $cache->add($key, 0);

        $incremented = $cache->increment($key);

        if (is_int($incremented)) {
            return;
        }

        $cache->forever($key, ((int) $cache->get($key, 0)) + 1);
    }
}
