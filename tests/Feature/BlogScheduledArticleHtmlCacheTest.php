<?php

declare(strict_types=1);

use Capell\Blog\Actions\EnsureBlogPublishingSurfaceAction;
use Capell\Blog\Actions\InvalidateDueScheduledArticleCachesAction;
use Capell\Blog\Data\BlogPublishingSurfaceRequestData;
use Capell\Blog\Models\Article;
use Capell\Blog\Support\Creator\BlogCreator;
use Capell\Core\Models\SiteDomain;
use Capell\HtmlCache\Support\Cache\HtmlCachePathResolver;
use Capell\HtmlCache\Support\Cache\HtmlCacheStore;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Storage;

use function Pest\Laravel\get;
use function Pest\Laravel\travelTo;

beforeEach(function (): void {
    Storage::fake('page_cache');
    // The store holds its disk from boot; rebuild it on the fake.
    app()->forgetInstance(HtmlCacheStore::class);
    config()->set('capell-html-cache.enabled', true);
    config()->set('queue.default', 'sync');
    config()->set('capell-html-cache.invalidation.mode', 'instant');
    Cache::forget(InvalidateDueScheduledArticleCachesAction::CHECKPOINT_CACHE_KEY);
});

/** @return array{domain: SiteDomain, article: Article} */
function scheduledArticleOnCachedSite(string $host, CarbonImmutable $visibleFrom, ?CarbonImmutable $visibleUntil = null): array
{
    $creator = resolve(BlogCreator::class);
    $domain = SiteDomain::factory()->default()->create(['domain' => $host, 'path' => null, 'scheme' => 'https']);
    EnsureBlogPublishingSurfaceAction::run(new BlogPublishingSurfaceRequestData(site: $domain->site));
    $article = Article::factory()
        ->site($domain->site)
        ->layout($creator->createArticleLayout())
        ->type($creator->createArticlePageType())
        ->withTranslations($domain->site->languages)
        ->create(['name' => 'Scheduled launch post', 'visible_from' => $visibleFrom, 'visible_until' => $visibleUntil]);

    return ['domain' => $domain, 'article' => $article];
}

/**
 * @param  list<string>  $paths
 * @return array<string, string>
 */
function seedCachedPages(SiteDomain $domain, array $paths): array
{
    $resolver = resolve(HtmlCachePathResolver::class);
    $files = [];
    foreach ($paths as $path) {
        $file = $resolver->pathForUrl($path, $domain);
        Storage::disk('page_cache')->put($file, 'Cached before the post was due');
        $files[$path] = $file;
    }

    return $files;
}

it('clears the cached post, blog index, feed and listing pages when the post becomes visible, so it appears at its scheduled minute', function (): void {
    $due = CarbonImmutable::parse('2026-06-01 09:00:00');
    travelTo($due->subMinutes(5));
    ['domain' => $domain, 'article' => $article] = scheduledArticleOnCachedSite('scheduled.example.test', $due);
    ['domain' => $otherDomain] = scheduledArticleOnCachedSite('other.example.test', $due->addDay());

    // Cached while the post was still scheduled: the index and feed omit it.
    expect(get('https://scheduled.example.test/blog/feed.xml')->assertOk()->content())->not->toContain('Scheduled launch post');

    $articlePath = $article->pageUrl()->value('url');
    expect($articlePath)->toBeString()->not->toBeEmpty();
    assert(is_string($articlePath));
    $files = seedCachedPages($domain, [$articlePath, '/blog', '/blog/feed.xml', '/blog/feed.atom', '/', '/news-widget-page']);
    $otherFiles = seedCachedPages($otherDomain, ['/blog', '/blog/feed.xml']);
    $disk = Storage::disk('page_cache');

    // The minute before it is due, nothing is cleared.
    travelTo($due->subMinute());
    InvalidateDueScheduledArticleCachesAction::run($due->subMinute());
    foreach ($files as $file) {
        expect($disk->exists($file))->toBeTrue();
    }

    // The scheduler's run at the due minute clears every cached page on the site.
    travelTo($due);
    expect(InvalidateDueScheduledArticleCachesAction::run($due))->toBe(1);
    foreach ($files as $path => $file) {
        expect($disk->exists($file))->toBeFalse("{$path} was still cached after the post became visible");
    }

    // Another site's cache is untouched.
    foreach ($otherFiles as $file) {
        expect($disk->exists($file))->toBeTrue();
    }

    // The next request renders fresh output that includes the post.
    expect(get('https://scheduled.example.test/blog/feed.xml')->assertOk()->content())->toContain('Scheduled launch post');
});

it('clears the cached pages when the post expires, so it disappears at its visible-until minute', function (): void {
    $expires = CarbonImmutable::parse('2026-06-01 18:00:00');
    travelTo($expires->subHour());
    ['domain' => $domain] = scheduledArticleOnCachedSite('expiring.example.test', $expires->subDays(3), $expires);
    $files = seedCachedPages($domain, ['/blog', '/blog/feed.xml']);
    $disk = Storage::disk('page_cache');

    travelTo($expires->subMinute());
    InvalidateDueScheduledArticleCachesAction::run($expires->subMinute());
    foreach ($files as $file) {
        expect($disk->exists($file))->toBeTrue();
    }

    travelTo($expires);
    expect(InvalidateDueScheduledArticleCachesAction::run($expires))->toBe(1);
    foreach ($files as $path => $file) {
        expect($disk->exists($file))->toBeFalse("{$path} was still cached after the post expired");
    }
});
