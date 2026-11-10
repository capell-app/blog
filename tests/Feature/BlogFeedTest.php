<?php

declare(strict_types=1);

use Capell\Blog\Actions\EnsureBlogPublishingSurfaceAction;
use Capell\Blog\Data\BlogPublishingSurfaceRequestData;
use Capell\Blog\Models\Article;
use Capell\Blog\Support\Creator\BlogCreator;
use Capell\Core\Contracts\PublicRead\EvaluatesPublicPageReadCandidatesIndividually;
use Capell\Core\Contracts\PublicRead\PublicPageReadEligibility;
use Capell\Core\Contracts\PublicRead\SchedulesPublicReadTransitions;
use Capell\Core\Data\PublicRead\PublicPageReadCandidateData;
use Capell\Core\Models\Site;
use Capell\Core\Models\SiteDomain;
use Capell\Core\Support\PublicRead\PublicPageReadEligibilityRegistry;
use Carbon\CarbonImmutable;

use function Pest\Laravel\get;

it('renders an RSS feed for published articles on the requested blog domain', function (): void {
    $fixture = blogFeedFixture();

    $xml = get('https://example.com/blog/feed.xml')
        ->assertOk()
        ->assertHeader('content-type', 'application/rss+xml; charset=UTF-8')
        ->content();

    expect($xml)
        ->toContain('<rss version="2.0">')
        ->toContain('<atom:link xmlns:atom="http://www.w3.org/2005/Atom" href="https://example.com/blog/feed.xml" rel="self" type="application/rss+xml" />')
        ->toContain('Useful &amp; Practical')
        ->toContain('https://example.com/blog/')
        ->not->toContain('Future article')
        ->not->toContain('Other site article')
        ->and(substr_count($xml, '<item>'))->toBe(1)
        ->and($fixture['site'])->toBeInstanceOf(Site::class);
});

it('renders an Atom feed variant for published blog articles', function (): void {
    blogFeedFixture();

    $xml = get('https://example.com/blog/feed.atom')
        ->assertOk()
        ->assertHeader('content-type', 'application/atom+xml; charset=UTF-8')
        ->content();

    expect($xml)
        ->toContain('<feed xmlns="http://www.w3.org/2005/Atom">')
        ->toContain('<link href="https://example.com/blog/feed.atom" rel="self" type="application/atom+xml" />')
        ->toContain('Useful &amp; Practical')
        ->not->toContain('Future article');
});

it('falls back to a short body excerpt when no summary was authored', function (): void {
    blogFeedFixture();

    $xml = get('https://example.com/blog/feed.xml')->assertOk()->content();

    expect($xml)
        ->toContain('<description>Summary with markup for XML escaping.</description>')
        ->not->toContain('<description></description>');
});

it('skips published articles without persisted page URLs from the blog feed', function (): void {
    $fixture = blogFeedFixture();

    $article = Article::factory()
        ->site($fixture['site'])
        ->withTranslations($fixture['site']->languages)
        ->create([
            'name' => 'Unlinked article',
            'visible_from' => CarbonImmutable::parse('2026-01-17 10:00:00'),
        ]);
    $article->translation()->update([
        'title' => 'Unlinked article',
        'content' => 'This article has no public URL.',
    ]);
    $article->pageUrls()->delete();
    $article->unsetRelation('pageUrl');

    expect($article->pageUrl)->toBeNull();

    $xml = get('https://example.com/blog/feed.xml')->assertOk()->content();

    expect($xml)
        ->not->toContain('Unlinked article')
        ->and(substr_count($xml, '<item>'))->toBe(1);
});

/**
 * @return array{site: Site}
 */
function blogFeedFixture(): array
{
    $blogCreator = resolve(BlogCreator::class);

    $siteDomain = SiteDomain::factory()
        ->default()
        ->create([
            'domain' => 'example.com',
            'path' => null,
            'scheme' => 'https',
        ]);
    $site = $siteDomain->site;

    EnsureBlogPublishingSurfaceAction::run(
        new BlogPublishingSurfaceRequestData(site: $site),
    );
    $articleType = $blogCreator->createArticlePageType();
    $articleLayout = $blogCreator->createArticleLayout();

    $publishedArticle = Article::factory()
        ->site($site)
        ->layout($articleLayout)
        ->type($articleType)
        ->withTranslations($site->languages)
        ->create([
            'name' => 'Useful Practical',
            'visible_from' => CarbonImmutable::parse('2026-01-15 10:00:00'),
        ]);
    $publishedArticle->translation()->update([
        'title' => 'Useful & Practical',
        'content' => 'Summary <strong>with markup</strong> for XML escaping.',
    ]);

    Article::factory()
        ->site($site)
        ->layout($articleLayout)
        ->type($articleType)
        ->withTranslations($site->languages)
        ->create([
            'name' => 'Future article',
            'visible_from' => now()->addDay(),
        ]);

    $otherDomain = SiteDomain::factory()
        ->default()
        ->create([
            'domain' => 'other.example.com',
            'path' => null,
            'scheme' => 'https',
        ]);

    Article::factory()
        ->site($otherDomain->site)
        ->layout($articleLayout)
        ->type($articleType)
        ->withTranslations($otherDomain->site->languages)
        ->create([
            'name' => 'Other site article',
            'visible_from' => CarbonImmutable::parse('2026-01-16 10:00:00'),
        ]);

    return ['site' => $site];
}

it('makes every cache revalidate the feed while public-read rules exist', function (): void {
    blogFeedFixture();
    blogFeedScheduledRules(120);

    $response = get('https://example.com/blog/feed.xml')->assertOk();
    $directives = blogFeedScheduledRulesDirectives($response->headers->get('Cache-Control'));

    // A page gated after publication must vanish from the next request, so no
    // cache may serve a copy it has not revalidated, whatever the schedule says.
    expect($directives['public'] ?? null)->toBeTrue()
        ->and($directives['no-cache'] ?? null)->toBeTrue()
        ->and($directives)->not->toHaveKeys(['max-age', 's-maxage', 'stale-while-revalidate', 'stale-if-error'])
        ->and($response->headers->get('ETag'))->not->toBeNull();
});

/** Rules whose next public-read transition is $seconds from now. */
function blogFeedScheduledRules(int $seconds): void
{
    resolve(PublicPageReadEligibilityRegistry::class)->register(new readonly class($seconds) implements PublicPageReadEligibility, SchedulesPublicReadTransitions
    {
        use EvaluatesPublicPageReadCandidatesIndividually;

        public function __construct(private int $seconds) {}

        public function key(): string
        {
            return 'scheduled-feed-test';
        }

        public function isPubliclyReadable(PublicPageReadCandidateData $candidate): bool
        {
            return true;
        }

        public function nextPublicReadTransitionAfter(CarbonImmutable $now, ?int $siteId = null): CarbonImmutable
        {
            return $now->addSeconds($this->seconds);
        }
    });
}

/** @return array<string, int|true> */
function blogFeedScheduledRulesDirectives(?string $header): array
{
    $directives = [];

    foreach (array_filter(array_map(trim(...), explode(',', (string) $header))) as $directive) {
        [$name, $value] = array_pad(explode('=', $directive, 2), 2, null);
        $directives[mb_strtolower((string) $name)] = $value === null ? true : (int) $value;
    }

    return $directives;
}
