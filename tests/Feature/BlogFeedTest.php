<?php

declare(strict_types=1);

use Capell\Blog\Models\Article;
use Capell\Blog\Support\Creator\BlogCreator;
use Capell\Core\Models\Site;
use Capell\Core\Models\SiteDomain;
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

    $blogCreator->createBlogPage($site);
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
