<?php

declare(strict_types=1);

namespace Capell\Blog\Actions;

use Capell\Blog\Enums\BlogPageTypeEnum;
use Capell\Blog\Enums\CacheEnum;
use Capell\Blog\Models\Article;
use Capell\Core\Facades\CapellCore;
use Capell\Core\Models\Language;
use Capell\Core\Models\Site;
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
            }

            $this->clearSiteLanguageAgnosticCache($siteId);
        }
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
        $cache = Cache::store();
        $cache->add($key, 0, null);

        $incremented = $cache->increment($key);

        if (is_int($incremented)) {
            return;
        }

        $cache->forever($key, ((int) $cache->get($key, 0)) + 1);
    }
}
