<?php

declare(strict_types=1);

namespace Capell\Blog\Actions;

use Capell\Blog\Enums\CacheEnum;
use Capell\Blog\Models\Article;
use Capell\Core\Facades\CapellCore;
use Capell\Core\Models\Language;
use Capell\Core\Models\Site;
use Capell\Tags\Models\Tag;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Lorisleiva\Actions\Concerns\AsObject;

final class ClearBlogTagCacheAction
{
    use AsObject;

    public function handle(Tag $tag): void
    {
        $siteIds = $this->affectedSiteIds($tag);

        foreach ($siteIds as $siteId) {
            $site = Site::query()
                ->with(['language', 'siteDomains.language'])
                ->find($siteId);

            if (! $site instanceof Site) {
                continue;
            }

            $site->getAllLanguages()
                ->each(function (Language $language) use ($siteId, $tag): void {
                    $languageId = (int) $language->getKey();

                    $this->incrementSiteTagsVersion($siteId, $languageId);

                    foreach ($this->slugsForLanguage($tag, $language->code) as $slug) {
                        CapellCore::removeCacheKey(CacheEnum::tagPage($siteId, $languageId, $slug));
                    }
                });
        }
    }

    /**
     * @return Collection<int, int>
     */
    private function affectedSiteIds(Tag $tag): Collection
    {
        $siteIds = collect([$tag->site_id, $tag->getOriginal('site_id')])
            ->filter(fn (mixed $siteId): bool => is_numeric($siteId))
            ->map(fn (mixed $siteId): int => (int) $siteId);

        if ($siteIds->isEmpty()) {
            $siteIds = Site::query()->pluck('id')->map(fn (mixed $siteId): int => (int) $siteId);
        }

        $articleMorphTypes = array_values(array_unique([
            Article::class,
            (new Article)->getMorphClass(),
        ]));

        $articleSiteIds = DB::table('taggables')
            ->join('pages', 'pages.id', '=', 'taggables.taggable_id')
            ->where('taggables.tag_id', $tag->getKey())
            ->whereIn('taggables.taggable_type', $articleMorphTypes)
            ->whereNotNull('pages.site_id')
            ->pluck('pages.site_id')
            ->map(fn (mixed $siteId): int => (int) $siteId);

        return $siteIds
            ->merge($articleSiteIds)
            ->filter(fn (int $siteId): bool => $siteId > 0)
            ->unique()
            ->values();
    }

    /**
     * @return list<string>
     */
    private function slugsForLanguage(Tag $tag, string $languageCode): array
    {
        $currentSlugs = $tag->getTranslations('slug');
        $originalSlugs = $this->normalizeSlugTranslations($tag->getOriginal('slug'));
        $rawOriginalSlugs = $this->normalizeSlugTranslations($tag->getRawOriginal('slug'));

        return collect([
            $tag->getTranslation('slug', $languageCode, false),
            $currentSlugs[$languageCode] ?? null,
            $originalSlugs[$languageCode] ?? null,
            $rawOriginalSlugs[$languageCode] ?? null,
        ])
            ->filter(fn (mixed $slug): bool => is_string($slug) && $slug !== '')
            ->unique()
            ->values()
            ->all();
    }

    /**
     * @return array<string, mixed>
     */
    private function normalizeSlugTranslations(mixed $slugs): array
    {
        if (is_array($slugs)) {
            return $slugs;
        }

        if (! is_string($slugs) || $slugs === '') {
            return [];
        }

        $decoded = json_decode($slugs, true);

        return is_array($decoded) ? $decoded : [];
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
