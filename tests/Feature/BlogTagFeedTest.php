<?php

declare(strict_types=1);

use Capell\Blog\Actions\EnsureBlogPublishingSurfaceAction;
use Capell\Blog\Data\BlogPublishingSurfaceRequestData;
use Capell\Blog\Models\Article;
use Capell\Blog\Support\Creator\BlogCreator;
use Capell\Blog\Support\Loader\TagLoader;
use Capell\Core\Models\Language;
use Capell\Core\Models\PageUrl;
use Capell\Core\Models\SiteDomain;
use Capell\Tags\Enums\TagTypeEnum;
use Capell\Tags\Models\Tag;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Relations\Relation;

use function Pest\Laravel\get;

it('renders an RSS feed limited to the requested tag', function (): void {
    $fixture = blogTagFeedScenario();

    $xml = get('https://example.com/blog/tag/' . $fixture['slug'] . '/feed.xml')
        ->assertOk()
        ->assertHeader('content-type', 'application/rss+xml; charset=UTF-8')
        ->content();

    expect($xml)
        ->toContain('<rss version="2.0">')
        ->toContain('href="https://example.com/blog/tag/' . $fixture['slug'] . '/feed.xml" rel="self"')
        ->toContain('Tagged &amp; Published')
        ->not->toContain('Untagged article')
        ->not->toContain('Future tagged article')
        ->and(substr_count($xml, '<item>'))->toBe(1);
});

it('includes the tag name in the feed title', function (): void {
    $fixture = blogTagFeedScenario();

    $xml = get('https://example.com/blog/tag/' . $fixture['slug'] . '/feed.xml')
        ->assertOk()
        ->content();

    expect($xml)->toContain('Blog: ' . $fixture['tagName'] . '</title>');
});

it('renders an Atom variant of the tag feed', function (): void {
    $fixture = blogTagFeedScenario();

    $xml = get('https://example.com/blog/tag/' . $fixture['slug'] . '/feed.atom')
        ->assertOk()
        ->assertHeader('content-type', 'application/atom+xml; charset=UTF-8')
        ->content();

    expect($xml)
        ->toContain('<feed xmlns="http://www.w3.org/2005/Atom">')
        ->toContain('<link href="https://example.com/blog/tag/' . $fixture['slug'] . '/feed.atom" rel="self" type="application/atom+xml" />')
        ->toContain('Tagged &amp; Published')
        ->not->toContain('Untagged article');
});

it('returns 404 for an unknown tag slug', function (): void {
    blogTagFeedScenario();

    get('https://example.com/blog/tag/does-not-exist/feed.xml')->assertNotFound();
});

it('links the channel at the tag html page, not at the feed', function (): void {
    $fixture = blogTagFeedScenario();

    $xml = get('https://example.com/blog/tag/' . $fixture['slug'] . '/feed.xml')
        ->assertOk()
        ->content();

    expect($fixture['tagPageUrl'])
        ->toStartWith('https://example.com/')
        ->toEndWith('/' . $fixture['slug'])
        ->and($xml)
        ->toContain('<link>' . $fixture['tagPageUrl'] . '</link>')
        ->not->toContain('<link>https://example.com/blog/tag/' . $fixture['slug'] . '/feed.xml</link>')
        ->and($xml)
        ->toContain('href="https://example.com/blog/tag/' . $fixture['slug'] . '/feed.xml" rel="self"');
});

it('falls back to the blog url when the tag results page url is unresolvable', function (): void {
    $fixture = blogTagFeedScenario(unpublishTagPage: true);

    $xml = get('https://example.com/blog/tag/' . $fixture['slug'] . '/feed.xml')
        ->assertOk()
        ->content();

    expect($xml)
        ->not->toContain($fixture['tagPageUrl'])
        ->and($xml)
        ->toContain('<link>https://example.com/blog</link>')
        ->not->toContain('<link>https://example.com/blog/tag/' . $fixture['slug'] . '/feed.xml</link>');
});

/**
 * @return array{slug: string, tagName: string, tagPageUrl: string}
 */
function blogTagFeedScenario(bool $unpublishTagPage = false): array
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
    $language = $siteDomain->language;

    expect($language)->toBeInstanceOf(Language::class);

    EnsureBlogPublishingSurfaceAction::run(
        new BlogPublishingSurfaceRequestData(site: $site),
    );
    $articleType = $blogCreator->createArticlePageType();
    $articleLayout = $blogCreator->createArticleLayout();

    $tag = Tag::factory()
        ->translate($language)
        ->type(TagTypeEnum::Page)
        ->create();

    $slug = (string) ($tag->getTranslations('slug')[$language->code] ?? '');
    $tagName = (string) ($tag->getTranslations('name')[$language->code] ?? '');

    expect($slug)->not->toBe('')
        ->and($tagName)->not->toBe('');

    $tagPage = blogTestPage(TagLoader::getTagResultsPage($site, $language));

    $tagPage->load(['pageUrl' => static function (Relation $query) use ($site, $language): void {
        $query->where('language_id', $language->id)
            ->where('site_id', $site->id)
            ->where('status', true);
    }]);
    $tagPage->pageUrl?->setRelation('siteDomain', $siteDomain);
    $tagPageUrl = $tag->getUrl($tagPage, $language);

    if ($unpublishTagPage) {
        PageUrl::query()
            ->where('pageable_type', $tagPage->getMorphClass())
            ->where('pageable_id', $tagPage->getKey())
            ->update(['status' => false]);
    }

    $tagged = Article::factory()
        ->site($site)
        ->layout($articleLayout)
        ->type($articleType)
        ->withTranslations($site->languages)
        ->hasAttached($tag)
        ->create([
            'name' => 'Tagged Published',
            'visible_from' => CarbonImmutable::parse('2026-01-15 10:00:00'),
        ]);
    $tagged->translation()->update([
        'title' => 'Tagged & Published',
        'content' => 'Tagged article body copy.',
    ]);

    Article::factory()
        ->site($site)
        ->layout($articleLayout)
        ->type($articleType)
        ->withTranslations($site->languages)
        ->hasAttached($tag)
        ->create([
            'name' => 'Future tagged article',
            'visible_from' => now()->addDay(),
        ]);

    Article::factory()
        ->site($site)
        ->layout($articleLayout)
        ->type($articleType)
        ->withTranslations($site->languages)
        ->create([
            'name' => 'Untagged article',
            'visible_from' => CarbonImmutable::parse('2026-01-16 10:00:00'),
        ]);

    return ['slug' => $slug, 'tagName' => $tagName, 'tagPageUrl' => $tagPageUrl];
}
