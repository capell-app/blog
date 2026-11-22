<?php

declare(strict_types=1);

use Capell\Blog\Actions\EnsureBlogPublishingSurfaceAction;
use Capell\Blog\Data\BlogPublishingSurfaceRequestData;
use Capell\Blog\Models\Article;
use Capell\Blog\Providers\BlogServiceProvider;
use Capell\Blog\Support\Creator\BlogCreator;
use Capell\Core\Data\Manifest\ExtensionPerformanceBudgetData;
use Capell\Core\Events\FrontendSurrogateKeysInvalidated;
use Capell\Core\Facades\CapellCore;
use Capell\Core\Models\Layout;
use Capell\Core\Models\Page;
use Capell\Core\Models\PageUrl;
use Capell\Core\Models\SiteDomain;
use Capell\Core\Support\PackageRegistry\CapellPackageRegistry;
use Capell\Core\Support\Publishing\ScheduledPublicationRegistry;
use Capell\Frontend\Actions\InvalidateDueScheduledPublicationCachesAction;
use Capell\Frontend\Actions\PersistScheduledPublicationInvalidationCheckpointAction;
use Capell\Frontend\Settings\FrontendSettings;
use Capell\HtmlCache\Actions\ClearCachedUrlsForSurrogateKeysAction;
use Capell\HtmlCache\Actions\WithdrawCachedUrlsForModelAction;
use Capell\HtmlCache\Models\CachedModelUrl;
use Capell\HtmlCache\Support\Cache\HtmlCacheDependencyTracking;
use Capell\HtmlCache\Support\Cache\HtmlCachePathResolver;
use Capell\HtmlCache\Support\Cache\HtmlCacheStore;
use Capell\Search\Actions\FlushSiteDiscoverySearchRegistryAction;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\Console\Kernel as ConsoleKernel;
use Illuminate\Foundation\Console\Kernel;
use Illuminate\Queue\Events\JobProcessed;
use Illuminate\Queue\Events\JobProcessing;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Sleep;

use function Pest\Laravel\get;
use function Pest\Laravel\travelTo;

beforeEach(function (): void {
    Storage::fake('page_cache');
    // The store holds its disk from boot; rebuild it on the fake.
    app()->forgetInstance(HtmlCacheStore::class);
    config()->set('capell-html-cache.enabled', true);
    config()->set('capell-html-cache.write_enabled', true);
    config()->set('queue.default', 'sync');
    config()->set('capell-html-cache.invalidation.mode', 'instant');

    // Keep the shipping cache policy. These fixtures represent cacheable Blog
    // renders and exercise the real middleware, dependency index and files.
    $registry = resolve(CapellPackageRegistry::class);
    $manifest = $registry->get('capell-app/blog');
    assert($manifest !== null);
    $manifest = clone $manifest;
    $performance = $manifest->performance->toArray();
    $performance['cacheSafety'] = [...$manifest->performance->cacheSafety->toArray(), 'cacheable' => true];
    $manifest->performance = ExtensionPerformanceBudgetData::fromArray($performance);
    $registry->register($manifest);
});

function scheduledPublicationConsoleKernel(): Kernel
{
    // Laravel disables Artisan lifecycle events in tests by default.
    $kernel = resolve(ConsoleKernel::class);
    assert($kernel instanceof Kernel);
    $kernel->rerouteSymfonyCommandEvents();
    $kernel->setArtisan(null);

    return $kernel;
}

function scheduledPublicationCheckpoint(): mixed
{
    return resolve(FrontendSettings::class)->getRepository()->getPropertyPayload(
        FrontendSettings::group(),
        'scheduled_publication_invalidation_checkpoint',
    );
}

function scheduledArticleCoverage(): mixed
{
    return resolve(FrontendSettings::class)->getRepository()
        ->getPropertiesInGroup('blog')['scheduled_article_publication_covered_until'] ?? null;
}

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

it('clears the cached post, blog index and listing pages when the post becomes visible', function (): void {
    $due = CarbonImmutable::parse('2026-06-01 09:00:00');
    travelTo($due->subMinutes(5));
    ['domain' => $domain, 'article' => $article] = scheduledArticleOnCachedSite('scheduled.example.test', $due);
    ['domain' => $otherDomain] = scheduledArticleOnCachedSite('other.example.test', $due->addDay());

    $feed = get('https://scheduled.example.test/blog/feed.xml')->assertOk();
    $feedEtag = $feed->headers->get('ETag');
    expect($feed->content())->not->toContain('Scheduled launch post')
        ->and($feedEtag)->toBeString();
    assert(is_string($feedEtag));

    $articlePath = $article->pageUrl()->value('url');
    expect($articlePath)->toBeString()->not->toBeEmpty();
    assert(is_string($articlePath));
    $files = seedCachedPages($domain, [$articlePath, '/blog', '/', '/news-widget-page']);
    $otherFiles = seedCachedPages($otherDomain, ['/blog']);
    $disk = Storage::disk('page_cache');

    // The minute before it is due, nothing is cleared.
    travelTo($due->subMinute());
    expect(InvalidateDueScheduledPublicationCachesAction::run($due->subMinute()))->toBe(0)
        ->and(Article::query()->published()->whereKey($article->getKey())->exists())->toBeFalse();
    foreach ($files as $file) {
        expect($disk->exists($file))->toBeTrue();
    }

    // The scheduler's run at the due minute clears every cached page on the site.
    travelTo($due);
    expect(InvalidateDueScheduledPublicationCachesAction::run($due))->toBe(1)
        ->and(Article::query()->published()->whereKey($article->getKey())->exists())->toBeTrue()
        ->and(resolve(FrontendSettings::class)->getRepository()->getPropertyPayload(
            FrontendSettings::group(),
            'scheduled_publication_invalidation_checkpoint',
        ))->toBe($due->toIso8601String());
    foreach ($files as $path => $file) {
        expect($disk->exists($file))->toBeFalse("{$path} was still cached after the post became visible");
    }

    // Another site's cache is untouched.
    foreach ($otherFiles as $file) {
        expect($disk->exists($file))->toBeTrue();
    }

    get('https://scheduled.example.test/blog/feed.xml', ['If-None-Match' => $feedEtag])
        ->assertOk()
        ->assertSee('Scheduled launch post');
    $feedPath = resolve(HtmlCachePathResolver::class)->pathForUrl('/blog/feed.xml', $domain);
    expect($disk->exists($feedPath))->toBeFalse();
});

it('withdraws exactly the warmed pages that show the expiring post', function (): void {
    $expires = CarbonImmutable::parse('2026-06-01 18:00:00');
    travelTo($expires->subHour());
    ['domain' => $domain, 'article' => $article] = scheduledArticleOnCachedSite('expiring.example.test', $expires->subDays(3), $expires);
    $listingLayout = Layout::factory()->create([
        'containers' => ['main' => ['widgets' => [['widget_key' => 'latest-articles']]]],
    ]);
    $listing = Page::factory()->site($domain->site)->layout($listingLayout)->withTranslations($domain->site->languages)->create(['visible_from' => $expires->subDays(4)]);
    PageUrl::factory()->page($listing)->site($domain->site)->language($domain->language)->create(['url' => '/news']);
    $unrelatedDomain = SiteDomain::factory()->default()->create(['domain' => 'unrelated.example.test', 'path' => null, 'scheme' => 'https']);
    $unrelated = Page::factory()->site($unrelatedDomain->site)->withTranslations($unrelatedDomain->site->languages)->create(['visible_from' => $expires->subDays(4)]);
    PageUrl::factory()->page($unrelated)->site($unrelatedDomain->site)->language($unrelatedDomain->language)->create(['url' => '/unrelated']);
    HtmlCacheDependencyTracking::markCurrent();

    $articlePath = $article->pageUrl()->value('url');
    assert(is_string($articlePath));
    $origin = 'https://expiring.example.test';
    $dependentPaths = [$articlePath, '/news'];
    foreach ($dependentPaths as $path) {
        get($origin . $path)->assertOk()->assertSeeText('Scheduled launch post');
    }
    get('https://unrelated.example.test/unrelated')->assertOk()->assertDontSeeText('Scheduled launch post');

    $dependants = CachedModelUrl::query()
        ->where('cacheable_type', $article->getMorphClass())
        ->where('cacheable_id', $article->id)
        ->pluck('path')->sort()->values()->all();
    expect($dependants)->toBe($dependentPaths);

    $resolver = resolve(HtmlCachePathResolver::class);
    $files = array_map(fn (string $path): string => $resolver->pathForUrl($path, $domain), $dependentPaths);
    $unrelatedFile = $resolver->pathForUrl('/unrelated', $unrelatedDomain);
    $disk = Storage::disk('page_cache');
    expect($disk->exists($unrelatedFile))->toBeTrue();

    travelTo($expires->subMinute());
    expect(InvalidateDueScheduledPublicationCachesAction::run($expires->subMinute()))->toBe(0)
        ->and(Article::query()->published()->whereKey($article->getKey())->exists())->toBeTrue();
    foreach ($files as $file) {
        expect($disk->exists($file))->toBeTrue();
    }

    travelTo($expires);
    expect(InvalidateDueScheduledPublicationCachesAction::run($expires))->toBe(1)
        ->and(Article::query()->published()->whereKey($article->getKey())->exists())->toBeFalse()
        ->and(resolve(FrontendSettings::class)->getRepository()->getPropertyPayload(
            FrontendSettings::group(),
            'scheduled_publication_invalidation_checkpoint',
        ))->toBe($expires->toIso8601String());
    foreach ($files as $file) {
        expect($disk->exists($file))->toBeFalse();
    }
    expect($disk->exists($unrelatedFile))->toBeTrue();
    get($origin . $articlePath)->assertNotFound();
    get($origin . '/news')->assertOk()->assertDontSeeText('Scheduled launch post');
});

it('catches up missed article expiries on the first shared sweep after upgrade', function (bool $hasLegacyCheckpoint, bool $hasSharedCheckpoint): void {
    config()->set('cache.default', 'array');
    $now = CarbonImmutable::parse('2026-06-01 18:00:00');
    // The legacy case is older than even the retired fallback window.
    $expires = $hasLegacyCheckpoint ? $now->subDays(2) : $now->subHour();
    travelTo($expires->subHour());
    ['domain' => $domain, 'article' => $article] = scheduledArticleOnCachedSite('upgrade.example.test', $expires->subDays(3), $expires);
    $file = seedCachedPages($domain, ['/blog'])['/blog'];
    $disk = Storage::disk('page_cache');
    expect($disk->exists($file))->toBeTrue()
        ->and(Article::query()->published()->whereKey($article->id)->exists())->toBeTrue();
    $legacyCheckpoint = $expires->subHour()->toIso8601String();
    if ($hasLegacyCheckpoint) {
        Cache::forever('capell:blog:scheduled-article-invalidation-checkpoint', $legacyCheckpoint);
    }
    if ($hasSharedCheckpoint) {
        PersistScheduledPublicationInvalidationCheckpointAction::run($now->subMinutes(10)->toIso8601String());
    }

    $kernel = scheduledPublicationConsoleKernel();

    $withdrawn = [];
    Event::listen(FrontendSurrogateKeysInvalidated::class, static function (FrontendSurrogateKeysInvalidated $event) use (&$withdrawn): void {
        if ($event->withdrawal !== null) {
            $withdrawn[] = [$event->withdrawal->morphClass, $event->withdrawal->modelKey];
        }
    });

    travelTo($now);
    if ($hasLegacyCheckpoint && ! $hasSharedCheckpoint) {
        $interrupt = true;
        Queue::before(static function (JobProcessing $event) use (&$interrupt): void {
            if ($interrupt && $event->job->resolveName() === WithdrawCachedUrlsForModelAction::class) {
                $interrupt = false;

                throw new RuntimeException('Interrupted while withdrawing the expired article');
            }
        });
        expect(fn (): int => $kernel->call('capell:invalidate-due-scheduled-publications'))
            ->toThrow(RuntimeException::class, 'Interrupted while withdrawing the expired article');
        expect($disk->exists($file))->toBeTrue()
            ->and(scheduledPublicationCheckpoint())->toBe($legacyCheckpoint)
            ->and(scheduledArticleCoverage())->toBe($legacyCheckpoint);
    }

    expect($kernel->call('capell:invalidate-due-scheduled-publications'))->toBe(0);

    expect($disk->exists($file))->toBeFalse()
        ->and(Article::query()->published()->whereKey($article->id)->exists())->toBeFalse()
        ->and($withdrawn)->toBe([[$article->getMorphClass(), $article->id]])
        ->and(resolve(FrontendSettings::class)->getRepository()->getPropertyPayload(
            FrontendSettings::group(),
            'scheduled_publication_invalidation_checkpoint',
        ))->toBe($now->toIso8601String());

    // Replaying the upgrade window would clear a newly rebuilt page on every tick.
    if (! $hasLegacyCheckpoint) {
        Cache::store('array')->flush();
    }
    seedCachedPages($domain, ['/blog']);
    travelTo($now->addMinute());
    expect($kernel->call('capell:invalidate-due-scheduled-publications'))->toBe(0);
    expect($disk->exists($file))->toBeTrue()
        ->and($withdrawn)->toHaveCount(1);
})->with([
    'legacy checkpoint' => [true, false],
    'no legacy checkpoint' => [false, false],
    'legacy and shared checkpoints' => [true, true],
    'shared checkpoint without legacy' => [false, true],
]);

it('withdraws an expired article after losing the shared checkpoint', function (?string $checkpoint): void {
    $expires = CarbonImmutable::parse('2026-06-01 18:00:00');
    $before = $expires->subHours(30);
    travelTo($before);
    $kernel = scheduledPublicationConsoleKernel();
    ['domain' => $domain, 'article' => $article] = scheduledArticleOnCachedSite('reset.example.test', $before->subDays(3), $expires);
    $articlePath = $article->pageUrl()->value('url');
    assert(is_string($articlePath));
    $url = 'https://reset.example.test' . $articlePath;
    expect($kernel->call('capell:invalidate-due-scheduled-publications'))->toBe(0);
    get($url)->assertOk()->assertSeeText('Scheduled launch post');
    $file = resolve(HtmlCachePathResolver::class)->pathForUrl($articlePath, $domain);
    expect(Storage::disk('page_cache')->exists($file))->toBeTrue();

    if ($checkpoint === null) {
        resolve(FrontendSettings::class)->getRepository()->deleteProperty(
            FrontendSettings::group(),
            'scheduled_publication_invalidation_checkpoint',
        );
    } else {
        PersistScheduledPublicationInvalidationCheckpointAction::run($checkpoint);
    }
    resolve(FrontendSettings::class)->refresh();
    $now = $expires->addHour();
    travelTo($now);

    expect($kernel->call('capell:invalidate-due-scheduled-publications'))->toBe(0)
        ->and(scheduledPublicationCheckpoint())->toBe($now->toIso8601String())
        ->and(scheduledArticleCoverage())->toBe($now->toIso8601String())
        ->and(Storage::disk('page_cache')->exists($file))->toBeFalse();
    get($url)->assertNotFound();
})->with([
    'missing setting' => [null],
    'empty' => [''],
    'malformed' => ['invalid'],
]);

it('withdraws articles that expired while Blog was absent from the shared sweep', function (): void {
    $expires = CarbonImmutable::parse('2026-06-01 18:00:00');
    $before = $expires->subDays(2);
    travelTo($before);
    $kernel = scheduledPublicationConsoleKernel();
    ['domain' => $domain, 'article' => $article] = scheduledArticleOnCachedSite('disabled.example.test', $before->subDays(3), $expires);
    $articlePath = $article->pageUrl()->value('url');
    assert(is_string($articlePath));
    $url = 'https://disabled.example.test' . $articlePath;
    expect($kernel->call('capell:invalidate-due-scheduled-publications'))->toBe(0)
        ->and(scheduledPublicationCheckpoint())->toBe($before->toIso8601String())
        ->and(scheduledArticleCoverage())->toBe($before->toIso8601String());
    get($url)->assertOk()->assertSeeText('Scheduled launch post');
    $file = resolve(HtmlCachePathResolver::class)->pathForUrl($articlePath, $domain);
    expect(Storage::disk('page_cache')->exists($file))->toBeTrue();

    // Keep the persisted state and replace the registry with a disabled boot's registry.
    $enabledRegistry = resolve(ScheduledPublicationRegistry::class);
    $disabledRegistry = new ScheduledPublicationRegistry;
    $disabledRegistry->freeze();
    CapellCore::forcePackageInstalled(BlogServiceProvider::$packageName, false);
    app()->instance(ScheduledPublicationRegistry::class, $disabledRegistry);
    $disabledTick = $expires->addHour();
    travelTo($disabledTick);
    expect($kernel->call('capell:invalidate-due-scheduled-publications'))->toBe(0)
        ->and(scheduledPublicationCheckpoint())->toBe($disabledTick->toIso8601String())
        ->and(scheduledArticleCoverage())->toBe($before->toIso8601String())
        ->and(Storage::disk('page_cache')->exists($file))->toBeTrue();

    CapellCore::forcePackageInstalled(BlogServiceProvider::$packageName);
    app()->instance(ScheduledPublicationRegistry::class, $enabledRegistry);
    $enabledTick = $disabledTick->addMinute();
    travelTo($enabledTick);
    expect($kernel->call('capell:invalidate-due-scheduled-publications'))->toBe(0)
        ->and(Storage::disk('page_cache')->exists($file))->toBeFalse()
        ->and(scheduledPublicationCheckpoint())->toBe($enabledTick->toIso8601String())
        ->and(scheduledArticleCoverage())->toBe($enabledTick->toIso8601String());
    get($url)->assertNotFound();
});

it('lets the shared sweep finish when another process holds the Blog coverage lock', function (): void {
    // Catches LockTimeoutException from the Blog listener aborting the whole shared sweep.
    $expires = CarbonImmutable::parse('2026-06-01 18:00:00');
    $before = $expires->subHour();
    travelTo($before);
    $kernel = scheduledPublicationConsoleKernel();
    ['domain' => $domain, 'article' => $article] = scheduledArticleOnCachedSite('locked.example.test', $before->subDays(3), $expires);
    $articlePath = $article->pageUrl()->value('url');
    assert(is_string($articlePath));
    expect($kernel->call('capell:invalidate-due-scheduled-publications'))->toBe(0)
        ->and(scheduledArticleCoverage())->toBe($before->toIso8601String());
    $file = seedCachedPages($domain, [$articlePath])[$articlePath];

    $tick = $expires->addMinute();
    travelTo($tick);
    Sleep::fake(syncWithCarbon: true);
    $held = Cache::lock('capell:blog:scheduled-article-publication-coverage', 600);
    expect($held->get())->toBeTrue();

    try {
        expect($kernel->call('capell:invalidate-due-scheduled-publications'))->toBe(0)
            ->and(Storage::disk('page_cache')->exists($file))->toBeFalse()
            ->and(scheduledArticleCoverage())->toBe($before->toIso8601String());
    } finally {
        $held->release();
    }
});

it('keeps rebuilt pages after a completed sweep even if the shared checkpoint is lost', function (): void {
    $expires = CarbonImmutable::parse('2026-06-01 18:00:00');
    travelTo($expires->subHour());
    $kernel = scheduledPublicationConsoleKernel();
    ['domain' => $domain, 'article' => $article] = scheduledArticleOnCachedSite('covered.example.test', $expires->subDays(3), $expires);
    $articlePath = $article->pageUrl()->value('url');
    assert(is_string($articlePath));
    $url = 'https://covered.example.test' . $articlePath;
    get($url)->assertOk()->assertSeeText('Scheduled launch post');
    $file = resolve(HtmlCachePathResolver::class)->pathForUrl($articlePath, $domain);
    $disk = Storage::disk('page_cache');
    expect($disk->exists($file))->toBeTrue();

    $coveredUntil = $expires->addSeconds(30);
    travelTo($coveredUntil);
    expect($kernel->call('capell:invalidate-due-scheduled-publications'))->toBe(0)
        ->and(scheduledPublicationCheckpoint())->toBe($coveredUntil->toIso8601String())
        ->and($disk->exists($file))->toBeFalse();
    get($url)->assertNotFound();
    $rebuilt = seedCachedPages($domain, ['/blog'])['/blog'];

    $idleTick = $coveredUntil->addSeconds(30);
    travelTo($idleTick);
    expect($kernel->call('capell:invalidate-due-scheduled-publications'))->toBe(0)
        ->and(scheduledPublicationCheckpoint())->toBe($coveredUntil->toIso8601String())
        ->and(scheduledArticleCoverage())->toBe($idleTick->toIso8601String());
    resolve(FrontendSettings::class)->getRepository()->updatePropertiesPayload(
        FrontendSettings::group(),
        ['scheduled_publication_invalidation_checkpoint' => null],
    );
    resolve(FrontendSettings::class)->refresh();
    $recoveryTick = $idleTick->addSeconds(30);
    travelTo($recoveryTick);
    expect($kernel->call('capell:invalidate-due-scheduled-publications'))->toBe(0)
        ->and($kernel->output())->toContain('Invalidated 0 scheduled publication cache entries.')
        ->and($disk->exists($rebuilt))->toBeTrue()
        ->and(scheduledPublicationCheckpoint())->toBe($idleTick->toIso8601String())
        ->and(scheduledArticleCoverage())->toBe($recoveryTick->toIso8601String());
});

it('refreshes archive counts and search before synchronous HTML invalidation finishes', function (): void {
    $due = CarbonImmutable::parse('2026-06-01 09:00:00');
    $archiveUrl = 'https://archive-order.example.test/blog/archives/2026-06';
    travelTo($due->subSeconds(20));
    config()->set('capell.disable_cache', false);
    CapellCore::forcePackageInstalled('capell-app/search', true);
    scheduledArticleOnCachedSite('archive-order.example.test', $due);
    get($archiveUrl)->assertNotFound();
    $searchVersion = FlushSiteDiscoverySearchRegistryAction::version();

    $requestsAfterPurge = 0;
    Queue::after(function (JobProcessed $event) use ($archiveUrl, $searchVersion, &$requestsAfterPurge): void {
        if ($event->job->resolveName() !== ClearCachedUrlsForSurrogateKeysAction::class) {
            return;
        }

        $requestsAfterPurge++;
        get($archiveUrl)->assertOk()->assertSeeText('Scheduled launch post');
        expect(FlushSiteDiscoverySearchRegistryAction::version())->toBeGreaterThan($searchVersion);
    });

    travelTo($due);
    expect(InvalidateDueScheduledPublicationCachesAction::run($due))->toBe(1)
        ->and($requestsAfterPurge)->toBe(1);
});
