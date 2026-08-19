<?php

declare(strict_types=1);

use Capell\Blog\Actions\GenerateArchiveUrlAction;
use Capell\Blog\Data\ArchiveMonthData;
use Capell\Blog\Models\Article;
use Capell\Blog\Support\Creator\BlogCreator;
use Capell\Core\Models\Language;
use Capell\Core\Models\Site;
use Capell\Core\Models\SiteDomain;
use Capell\Frontend\Enums\CacheEnum;
use Capell\Tags\Enums\TagTypeEnum;
use Capell\Tags\Models\Tag;
use Capell\Tests\Support\Concerns\TestingFrontend;
use Carbon\CarbonImmutable;
use Illuminate\Testing\TestResponse;

use function Pest\Laravel\get;

uses(TestingFrontend::class);

beforeEach(function (): void {
    config(['capell.disable_cache_save_keys' => [CacheEnum::Pages->value . '-*']]);
});

/**
 * @return list<array{rel: string, href: string, tag: string}>
 */
function blogRelPaginationLinks(TestResponse $response): array
{
    $html = (string) $response->getContent();

    preg_match_all('/<link rel="(prev|next)" href="([^"]*)">/', $html, $matches, PREG_SET_ORDER);

    return array_map(
        static fn (array $match): array => ['rel' => $match[1], 'href' => $match[2], 'tag' => $match[0]],
        $matches,
    );
}

test('paginated blog listing emits rel prev and next link tags', function (): void {
    $blogCreator = resolve(BlogCreator::class);

    $siteDomain = SiteDomain::factory()->default()->create();
    $site = $siteDomain->site;

    $blogPage = $blogCreator->createBlogPage($site, meta: ['limit' => 1]);
    $blogUrl = blogTestPageUrl($blogPage->pageUrl);
    $articleType = $blogCreator->createArticlePageType();
    $articleLayout = $blogCreator->createArticleLayout();

    Article::factory()
        ->site($site)
        ->layout($articleLayout)
        ->type($articleType)
        ->withTranslations($site->languages)
        ->forEachSequence(
            ['visible_from' => '2023-01-01'],
            ['visible_from' => '2023-02-01'],
            ['visible_from' => '2023-03-01'],
        )
        ->create();

    $base = $blogUrl->full_url;

    expect(blogRelPaginationLinks(get($base)->assertOk()))->toBe([
        ['rel' => 'next', 'href' => $base . '/2', 'tag' => '<link rel="next" href="' . $base . '/2">'],
    ]);

    expect(blogRelPaginationLinks(get($base . '/2')->assertOk()))->toBe([
        ['rel' => 'prev', 'href' => $base, 'tag' => '<link rel="prev" href="' . $base . '">'],
        ['rel' => 'next', 'href' => $base . '/3', 'tag' => '<link rel="next" href="' . $base . '/3">'],
    ]);

    expect(blogRelPaginationLinks(get($base . '/3')->assertOk()))->toBe([
        ['rel' => 'prev', 'href' => $base . '/2', 'tag' => '<link rel="prev" href="' . $base . '/2">'],
    ]);
});

test('single article page emits no pagination rel link tags', function (): void {
    $blogCreator = resolve(BlogCreator::class);

    $siteDomain = SiteDomain::factory()->default()->create();
    $site = $siteDomain->site;

    $blogPage = $blogCreator->createBlogPage($site, meta: ['limit' => 1]);
    $archivesPage = $blogCreator->createArchivesPage($blogPage);
    $blogCreator->createArchivePage($archivesPage);
    $tagsPage = $blogCreator->createTagsPage($site, $blogPage);
    $blogCreator->createTagPage($site, $tagsPage);
    $articleType = $blogCreator->createArticlePageType();
    $articleLayout = $blogCreator->createArticleLayout();

    $article = Article::factory()
        ->site($site)
        ->layout($articleLayout)
        ->type($articleType)
        ->withTranslations($site->languages)
        ->create(['visible_from' => '2023-01-01']);

    $articleUrl = blogTestPageUrl(blogTestArticle($article)->pageUrl);

    expect(blogRelPaginationLinks(get($articleUrl->full_url)->assertOk()))->toBe([]);
});

test('paginated tag listing emits a rel next link tag', function (): void {
    $blogCreator = resolve(BlogCreator::class);

    $language = Language::factory()->create();
    $site = Site::factory()->recycle($language)->withTranslations()->create();

    $blogPage = $blogCreator->createBlogPage($site);
    $tagsPage = $blogCreator->createTagsPage($site, $blogPage);
    $tagPage = $blogCreator->createTagPage($site, $tagsPage);

    $tagBlueprint = $tagPage->blueprint;
    $tagBlueprint?->forceFill([
        'meta' => [
            ...($tagBlueprint->meta ?? []),
            'limit' => 1,
        ],
    ])->saveQuietly();

    $tagPage->forceFill([
        'meta' => [
            ...($tagPage->meta ?? []),
            'limit' => 1,
        ],
    ])->saveQuietly();

    $tag = Tag::factory()->translate($language)->type(TagTypeEnum::Page)->create();

    Article::factory()
        ->site($site)
        ->withTranslations()
        ->hasAttached($tag)
        ->forEachSequence(
            ['visible_from' => '2023-01-01'],
            ['visible_from' => '2023-02-01'],
        )
        ->create();

    $links = blogRelPaginationLinks(get($tag->getUrl($tagPage, $language))->assertOk());

    expect($links)->toHaveCount(1)
        ->and($links[0]['rel'])->toBe('next');
});

test('paginated date archive listing emits a rel next link tag', function (): void {
    $blogCreator = resolve(BlogCreator::class);

    $siteDomain = SiteDomain::factory()->default()->create();
    $site = $siteDomain->site;

    $blogPage = $blogCreator->createBlogPage($site);
    $archivesPage = $blogCreator->createArchivesPage($blogPage);
    $archivePage = $blogCreator->createArchivePage($archivesPage);
    $blogCreator->createArticlePageType();
    $articleLayout = $blogCreator->createArticleLayout();
    $archivePageUrl = blogTestPageUrl($archivePage->pageUrl);

    $archivePage->forceFill([
        'meta' => [
            ...($archivePage->meta ?? []),
            'limit' => 1,
        ],
    ])->saveQuietly();

    $publishDate = CarbonImmutable::now()->subMonth();

    Article::factory()
        ->count(2)
        ->site($site)
        ->layout($articleLayout)
        ->withTranslations($site->languages)
        ->state([
            'visible_from' => fake()->dateTimeBetween($publishDate->startOfMonth(), $publishDate->endOfMonth()),
        ])
        ->create();

    $archiveUrl = GenerateArchiveUrlAction::run($archivePageUrl, ArchiveMonthData::fromDate($publishDate));

    $links = blogRelPaginationLinks(get($archiveUrl)->assertOk());

    expect($links)->toHaveCount(1)
        ->and($links[0]['rel'])->toBe('next');
});

test('pagination rel link tags leak no editor or package internals', function (): void {
    $blogCreator = resolve(BlogCreator::class);

    $siteDomain = SiteDomain::factory()->default()->create();
    $site = $siteDomain->site;

    $blogPage = $blogCreator->createBlogPage($site, meta: ['limit' => 1]);
    $blogUrl = blogTestPageUrl($blogPage->pageUrl);
    $articleType = $blogCreator->createArticlePageType();
    $articleLayout = $blogCreator->createArticleLayout();

    Article::factory()
        ->site($site)
        ->layout($articleLayout)
        ->type($articleType)
        ->withTranslations($site->languages)
        ->forEachSequence(
            ['visible_from' => '2023-01-01'],
            ['visible_from' => '2023-02-01'],
            ['visible_from' => '2023-03-01'],
        )
        ->create();

    $links = blogRelPaginationLinks(get($blogUrl->full_url . '/2')->assertOk());

    expect($links)->toHaveCount(2);

    foreach ($links as $link) {
        expect($link['tag'])->toMatch('#^<link rel="(prev|next)" href="' . preg_quote($blogUrl->full_url, '#') . '(/\d+)?">$#')
            ->and($link['href'])->not->toContain('admin')
            ->and($link['href'])->not->toContain('blueprint')
            ->and($link['href'])->not->toContain('capell-')
            ->and($link['href'])->not->toContain('signature')
            ->and($link['href'])->not->toContain('id=')
            ->and($link['href'])->not->toContain('livewire');
    }
});
