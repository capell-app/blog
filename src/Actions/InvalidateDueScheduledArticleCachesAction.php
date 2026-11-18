<?php

declare(strict_types=1);

namespace Capell\Blog\Actions;

use Capell\Blog\Models\Article;
use Capell\Core\Data\PublicModelWithdrawalData;
use Capell\Core\Events\FrontendSurrogateKeysInvalidated;
use Capell\Frontend\Support\Cache\CacheInvalidationRegistry;
use Carbon\CarbonImmutable;
use DateTimeInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Cache;
use Lorisleiva\Actions\Concerns\AsFake;
use Lorisleiva\Actions\Concerns\AsObject;

/**
 * Clears public caches when an article's visible_from or visible_until passes.
 *
 * Core's scheduled-publication sweep only knows pages, and articles live in their own
 * table, so without this nothing cleared caches when a scheduled article went live or an
 * expiring article left public view. Each run covers the time since the last run, with a
 * short overlap so an article due at the boundary is never skipped.
 *
 * @method static int run(?CarbonImmutable $until = null)
 */
final class InvalidateDueScheduledArticleCachesAction
{
    use AsFake;
    use AsObject;

    public const string CHECKPOINT_CACHE_KEY = 'capell:blog:scheduled-article-invalidation-checkpoint';

    /** Used when no checkpoint survives (first run, evicted cache): wide enough to cover a deploy or scheduler gap. */
    private const int FALLBACK_SCAN_MINUTES = 1440;

    private const int OVERLAP_SECONDS = 5;

    public function handle(?CarbonImmutable $until = null): int
    {
        $until ??= CarbonImmutable::now();
        $from = $this->from($until);
        $invalidated = 0;

        Article::query()
            ->where(static function (Builder $query) use ($from, $until): void {
                $query->whereBetween('visible_from', [$from, $until])
                    ->orWhereBetween('visible_until', [$from, $until]);
            })
            ->chunkById(100, function ($articles) use (&$invalidated, $until): void {
                foreach ($articles as $article) {
                    ClearBlogContentCacheAction::run($article);
                    resolve(CacheInvalidationRegistry::class)->invalidateChangedModel($article);
                    event(new FrontendSurrogateKeysInvalidated(
                        [sprintf('page-%d', $article->id), sprintf('site-%d', $article->site_id)],
                        $this->isPublicAt($article, $until) ? null : PublicModelWithdrawalData::forModel($article),
                    ));
                    $invalidated++;
                }
            });

        Cache::forever(self::CHECKPOINT_CACHE_KEY, $until->toIso8601String());

        return $invalidated;
    }

    private function from(CarbonImmutable $until): CarbonImmutable
    {
        $checkpoint = Cache::get(self::CHECKPOINT_CACHE_KEY);

        if (is_string($checkpoint) && CarbonImmutable::hasFormat($checkpoint, DateTimeInterface::ATOM)) {
            $from = CarbonImmutable::createFromFormat(DateTimeInterface::ATOM, $checkpoint);

            if ($from instanceof CarbonImmutable && $from->lessThanOrEqualTo($until)) {
                return $from->subSeconds(self::OVERLAP_SECONDS);
            }
        }

        return $until->subMinutes(self::FALLBACK_SCAN_MINUTES);
    }

    /** An article due in this window that is not public now has expired rather than been published. */
    private function isPublicAt(Article $article, CarbonImmutable $until): bool
    {
        return ($article->visible_from === null || $article->visible_from->lessThanOrEqualTo($until))
            && ($article->visible_until === null || $article->visible_until->greaterThan($until));
    }
}
