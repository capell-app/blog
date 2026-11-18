<?php

declare(strict_types=1);

use Capell\Blog\Actions\ApplyRelatedArticleRankingAction;
use Capell\Blog\Actions\BuildArticleMetaDataAction;
use Capell\Blog\Actions\EnsureBlogPublishingSurfaceAction;
use Capell\Blog\Actions\InvalidateDueScheduledArticleCachesAction;
use Capell\Blog\Data\BlogPublishingSurfaceRequestData;
use Capell\Blog\Models\Article;
use Capell\Blog\Support\Creator\BlogCreator;
use Capell\Core\Events\FrontendSurrogateKeysInvalidated;
use Capell\Core\Facades\CapellCore;
use Capell\Core\Models\SiteDomain;
use Capell\Search\Actions\FlushSiteDiscoverySearchRegistryAction;
use Capell\Tags\Enums\TagTypeEnum;
use Capell\Tags\Models\Tag;
use Capell\Tests\Fixtures\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Event;

use function Pest\Laravel\get;

/** @return array{domain: SiteDomain, make: Closure(array<string, mixed>): Article} */
function scheduledBlogFixture(string $host = 'example.com'): array
{
    $creator = resolve(BlogCreator::class);
    $domain = SiteDomain::factory()->default()->create(['domain' => $host, 'path' => null, 'scheme' => 'https']);
    $site = $domain->site;
    EnsureBlogPublishingSurfaceAction::run(new BlogPublishingSurfaceRequestData(site: $site));
    $type = $creator->createArticlePageType();
    $layout = $creator->createArticleLayout();

    return [
        'domain' => $domain,
        'make' => static fn (array $attributes): Article => Article::factory()->site($site)->layout($layout)->type($type)->withTranslations($site->languages)->create($attributes),
    ];
}

beforeEach(function (): void {
    Cache::forget(InvalidateDueScheduledArticleCachesAction::CHECKPOINT_CACHE_KEY);
});

it('sweeps caches when an article becomes visible, and withdraws it when it expires', function (): void {
    Event::fake([FrontendSurrogateKeysInvalidated::class]);
    $fixture = scheduledBlogFixture();
    $now = CarbonImmutable::parse('2026-06-01 12:00:00');
    $going = $fixture['make'](['name' => 'Going live', 'visible_from' => $now->subSeconds(30)]);
    $expiring = $fixture['make'](['name' => 'Expiring', 'visible_from' => $now->subDays(3), 'visible_until' => $now->subSeconds(20)]);
    $fixture['make'](['name' => 'Long ago', 'visible_from' => $now->subDays(9)]);
    $fixture['make'](['name' => 'Later', 'visible_from' => $now->addDay()]);

    expect(InvalidateDueScheduledArticleCachesAction::run($now))->toBe(2);

    Event::assertDispatched(FrontendSurrogateKeysInvalidated::class, static fn (FrontendSurrogateKeysInvalidated $event): bool => in_array(sprintf('page-%d', $going->id), $event->surrogateKeys, true) && $event->withdrawal === null);
    Event::assertDispatched(FrontendSurrogateKeysInvalidated::class, static fn (FrontendSurrogateKeysInvalidated $event): bool => in_array(sprintf('page-%d', $expiring->id), $event->surrogateKeys, true) && $event->withdrawal?->modelKey === $expiring->id);
    // The checkpoint advances, so the same transitions are not swept again a minute later.
    expect(InvalidateDueScheduledArticleCachesAction::run($now->addMinute()))->toBe(0);
});

it('clears only the cached pages of the site whose article became visible', function (): void {
    $first = scheduledBlogFixture();
    $second = scheduledBlogFixture('other.example.com');
    $now = CarbonImmutable::parse('2026-06-01 12:00:00');
    $going = $first['make'](['name' => 'Going live', 'visible_from' => $now->subSeconds(30)]);
    $second['make'](['name' => 'Other site later', 'visible_from' => $now->addDay()]);

    Event::fake([FrontendSurrogateKeysInvalidated::class]);

    expect(InvalidateDueScheduledArticleCachesAction::run($now))->toBe(1);

    Event::assertDispatchedTimes(FrontendSurrogateKeysInvalidated::class, 1);
    Event::assertDispatched(FrontendSurrogateKeysInvalidated::class, static fn (FrontendSurrogateKeysInvalidated $event): bool => $event->surrogateKeys === [sprintf('page-%d', $going->id), sprintf('site-%d', $going->site_id)]);
    expect($second['domain']->site_id)->not->toBe($going->site_id);
    expect(InvalidateDueScheduledArticleCachesAction::run($now->addSeconds(30)))->toBe(0);
});

it('sweeps transitions missed while no checkpoint existed', function (): void {
    Event::fake([FrontendSurrogateKeysInvalidated::class]);
    $fixture = scheduledBlogFixture();
    $now = CarbonImmutable::parse('2026-06-01 12:00:00');
    $fixture['make'](['name' => 'Went live during an outage', 'visible_from' => $now->subHours(5)]);

    expect(InvalidateDueScheduledArticleCachesAction::run($now))->toBe(1);
});

it('leaves scheduled and expired articles out of the feed', function (): void {
    $fixture = scheduledBlogFixture();
    $fixture['make'](['name' => 'Live', 'visible_from' => now()->subDay()]);
    $fixture['make'](['name' => 'Scheduled', 'visible_from' => now()->addDay()]);
    $fixture['make'](['name' => 'Expired', 'visible_from' => now()->subDays(5), 'visible_until' => now()->subDay()]);

    $xml = get('https://example.com/blog/feed.xml')->assertOk()->content();

    expect(substr_count($xml, '<item>'))->toBe(1)
        ->and($xml)->not->toContain('Scheduled')->not->toContain('Expired');
});

it('strips control characters so one bad character cannot break the feed', function (): void {
    $fixture = scheduledBlogFixture();
    $article = $fixture['make'](['name' => 'Live', 'visible_from' => now()->subDay()]);
    $article->translation()->update(['title' => "Bad\x0Bchar \u{FFFE}here"]);

    $xml = get('https://example.com/blog/feed.xml')->assertOk()->content();

    expect(simplexml_load_string($xml))->not->toBeFalse()
        ->and($xml)->toContain('Badchar here');
});

it('puts the author and last update on Atom entries', function (): void {
    $fixture = scheduledBlogFixture();
    $author = User::factory()->create(['name' => 'Ada Lovelace']);
    $article = $fixture['make'](['name' => 'Live', 'visible_from' => now()->subDays(2)]);
    $article->forceFill(['created_by' => $author->getKey(), 'updated_at' => now()->subHour()])->saveQuietly();

    $xml = get('https://example.com/blog/feed.atom')->assertOk()->content();

    expect($xml)->toContain('<author><name>Ada Lovelace</name></author>')
        ->toContain('<published>')
        ->toContain('<updated>' . $article->refresh()->updated_at?->toAtomString() . '</updated>');
});

it('honours the configured feed limit', function (): void {
    config(['capell-blog.feed_limit' => 1]);
    $fixture = scheduledBlogFixture();
    $fixture['make'](['name' => 'One', 'visible_from' => now()->subDays(2)]);
    $fixture['make'](['name' => 'Two', 'visible_from' => now()->subDay()]);

    expect(substr_count(get('https://example.com/blog/feed.xml')->assertOk()->content(), '<item>'))->toBe(1);
});

it('relates articles by shared tags, most shared first, and nothing for an untagged article', function (): void {
    $fixture = scheduledBlogFixture();
    $site = $fixture['domain']->site;
    $language = $site->language;
    /** @var list<Tag> $tags */
    $tags = array_map(fn (): Tag => Tag::factory()->site($site)->translate($language)->type(TagTypeEnum::Page)->create(), range(1, 3));
    $current = $fixture['make'](['name' => 'Current', 'visible_from' => now()->subDays(9)]);
    $one = $fixture['make'](['name' => 'One shared', 'visible_from' => now()->subDays(8)]);
    $two = $fixture['make'](['name' => 'Two shared', 'visible_from' => now()->subDays(7)]);
    $none = $fixture['make'](['name' => 'No shared', 'visible_from' => now()->subDays(6)]);
    $current->tags()->attach([$tags[0]->getKey(), $tags[1]->getKey()]);
    $one->tags()->attach([$tags[0]->getKey()]);
    // $one is newer than $two, so newest-first ordering alone would put it first.
    $one->forceFill(['visible_from' => now()->subDay()])->saveQuietly();
    $two->tags()->attach([$tags[0]->getKey(), $tags[1]->getKey()]);
    $none->tags()->attach([$tags[2]->getKey()]);

    $related = ApplyRelatedArticleRankingAction::run(Article::query()->publishedLatest(), $current->getKey(), [$tags[0]->getKey(), $tags[1]->getKey()])->pluck('id')->all();
    $untagged = ApplyRelatedArticleRankingAction::run(Article::query(), $current->getKey(), [])->count();

    expect($related)->toBe([$two->getKey(), $one->getKey()])
        ->and($untagged)->toBe(0);
});

it('counts reading time for scripts written without spaces', function (string $content, int $minutes): void {
    $method = new ReflectionMethod(BuildArticleMetaDataAction::class, 'readingTimeMinutes');

    expect($method->invoke(new BuildArticleMetaDataAction, $content))->toBe($minutes);
})->with([
    'latin' => [str_repeat('word ', 400), 2],
    'cyrillic' => [str_repeat('слово ', 400), 2],
    'chinese' => [str_repeat('字', 800), 2],
    'one word' => ['<p>Hello</p>', 1],
]);

it('moves the search registry version on when an article is published or unpublished', function (): void {
    CapellCore::forcePackageInstalled('capell-app/search', true);
    $fixture = scheduledBlogFixture();
    $article = $fixture['make'](['name' => 'Draft', 'visible_from' => now()->addDay()]);

    $beforePublish = FlushSiteDiscoverySearchRegistryAction::version();
    $article->update(['visible_from' => now()->subMinute()]);
    $afterPublish = FlushSiteDiscoverySearchRegistryAction::version();
    $article->update(['visible_until' => now()->subSecond()]);
    $afterUnpublish = FlushSiteDiscoverySearchRegistryAction::version();

    expect($afterPublish)->toBeGreaterThan($beforePublish)
        ->and($afterUnpublish)->toBeGreaterThan($afterPublish);
});

it('moves the search registry version on when the sweep passes a scheduled publication', function (): void {
    CapellCore::forcePackageInstalled('capell-app/search', true);
    $fixture = scheduledBlogFixture();
    $now = CarbonImmutable::parse('2026-06-01 12:00:00');
    $fixture['make'](['name' => 'Going live', 'visible_from' => $now->subSeconds(30)]);
    $before = FlushSiteDiscoverySearchRegistryAction::version();

    InvalidateDueScheduledArticleCachesAction::run($now);

    expect(FlushSiteDiscoverySearchRegistryAction::version())->toBeGreaterThan($before);
});

it('leaves the search registry alone when search is not installed', function (): void {
    CapellCore::forcePackageInstalled('capell-app/search', false);
    $fixture = scheduledBlogFixture();
    $before = FlushSiteDiscoverySearchRegistryAction::version();

    $fixture['make'](['name' => 'Live', 'visible_from' => now()->subDay()]);

    expect(FlushSiteDiscoverySearchRegistryAction::version())->toBe($before);
});

it('declares search as an optional package bridge', function (): void {
    $packagePath = dirname(__DIR__, 2);
    /** @var array{dependencies: array{supports: list<string>}} $capell */
    $capell = json_decode((string) file_get_contents($packagePath . '/capell.json'), true, flags: JSON_THROW_ON_ERROR);
    /** @var array{suggest: array<string, string>, require: array<string, string>} $composer */
    $composer = json_decode((string) file_get_contents($packagePath . '/composer.json'), true, flags: JSON_THROW_ON_ERROR);

    expect($capell['dependencies']['supports'])->toContain('capell-app/search')
        ->and($composer['suggest'])->toHaveKey('capell-app/search')
        ->and($composer['require'])->not->toHaveKey('capell-app/search');
});
