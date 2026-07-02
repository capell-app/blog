<?php

declare(strict_types=1);

use Capell\Blog\Actions\GenerateArchiveUrlAction;
use Capell\Blog\Data\ArchiveMonthData;
use Capell\Blog\Models\Article;
use Capell\Blog\Support\Creator\BlogCreator;
use Capell\Blog\Support\Sitemap\ArticlesSitemap;
use Capell\Core\Models\Language;
use Capell\Core\Models\Page;
use Capell\Core\Models\Site;
use Capell\Core\Models\SiteDomain;
use Capell\Frontend\Enums\CacheEnum;
use Capell\Tags\Enums\TagTypeEnum;
use Capell\Tags\Models\Tag;
use Capell\Tests\Support\Concerns\TestingFrontend;
use Carbon\CarbonImmutable;

use function Pest\Laravel\get;

use Sinnbeck\DomAssertions\Asserts\AssertElement;
use Sinnbeck\DomAssertions\Asserts\BaseAssert;

uses(TestingFrontend::class);

beforeEach(function (): void {
    config(['capell-core.disable_cache_save_keys' => [CacheEnum::Pages->value . '-*']]);
});

test('blog page lists articles', function (): void {
    $blogCreator = resolve(BlogCreator::class);

    $siteDomain = SiteDomain::factory()->default()->create();
    $site = $siteDomain->site;

    $blogPage = $blogCreator->createBlogPage($site);
    $blogUrl = blogTestPageUrl($blogPage->pageUrl);

    $articleType = $blogCreator->createArticlePageType();
    $articleLayout = $blogCreator->createArticleLayout();

    $archivesPage = $blogCreator->createArchivesPage($blogPage);
    $blogCreator->createArchivePage($archivesPage);

    $tagsPage = $blogCreator->createTagsPage($site, $blogPage);
    $blogCreator->createTagPage($site, $tagsPage);

    $articles = Article::factory()
        ->count(3)
        ->site($siteDomain->site)
        ->layout($articleLayout)
        ->type($articleType)
        ->withTranslations($site->languages)
        ->withExampleImages()
        ->forEachSequence(
            ['visible_from' => '2023-01-01'],
            ['visible_from' => '2023-02-01'],
            ['visible_from' => '2023-03-01'],
        )
        ->create();

    $articles = $articles->reverse()->values();

    expect($blogPage)
        ->toBeInstanceOf(Page::class)
        ->type->name->toBe('Blog')
        ->layout->name->toBe('Blog Posts');

    get($blogUrl->full_url)
        ->assertOk()
        ->assertElementExists(
            'title',
            fn (AssertElement $elm): BaseAssert => $elm->containsText($blogPage->title . ' | ' . $site->title),
        )
        ->assertElementExists(
            'body',
            fn (AssertElement $elm): BaseAssert => $elm
                ->contains('h1', count: 1)
                ->containsText((string) blogTestTranslation($blogPage->translation)->title)
                ->doesntContain('Article Archives'),
        )
        ->assertElementExists('img[src^="https://images.unsplash.com"]')
        ->assertElementExists(
            '.results',
            fn (AssertElement $elm): BaseAssert => $elm->doesntContain('.no-results')
                ->contains('.asset-index', count: $articles->count())
                ->each(
                    '.asset-index',
                    function (AssertElement $titleElm, int $index) use ($articles): BaseAssert {
                        $article = blogTestArticle($articles->get($index));

                        return $titleElm->containsText((string) blogTestTranslation($article->translation)->title)
                            ->find(
                                'a',
                                fn (AssertElement $linkElm): BaseAssert => $linkElm->has(
                                    'href',
                                    blogTestPageUrl($article->pageUrl)->full_url,
                                ),
                            );
                    },
                ),
        );
});

test('articles sitemap nests published articles below the blog page', function (): void {
    $blogCreator = resolve(BlogCreator::class);

    $siteDomain = SiteDomain::factory()->default()->create();
    $site = $siteDomain->site;

    $blogPage = $blogCreator->createBlogPage($site);
    $articleType = $blogCreator->createArticlePageType();
    $articleLayout = $blogCreator->createArticleLayout();

    $article = Article::factory()
        ->site($site)
        ->layout($articleLayout)
        ->type($articleType)
        ->withTranslations($site->languages)
        ->create();

    $sitemapPages = (new ArticlesSitemap($site, $siteDomain, $siteDomain->language))->fetch();
    $blogNode = $sitemapPages->first();

    expect($sitemapPages)->toHaveCount(1)
        ->and($blogNode->pageId)->toBe($blogPage->id)
        ->and($blogNode->children)->toHaveCount(1)
        ->and($blogNode->children->first()->pageId)->toBe($article->id)
        ->and($blogNode->children->first()->url)->toBe(blogTestPageUrl($article->pageUrl)->full_url);
});

test('visit blogs page with no articles and see appropriate message', function (): void {
    $blogCreator = resolve(BlogCreator::class);

    $siteDomain = SiteDomain::factory()->default()->create();
    $site = $siteDomain->site;

    $blogPage = $blogCreator->createBlogPage($site);
    $blogUrl = blogTestPageUrl($blogPage->pageUrl);

    expect($blogPage)
        ->toBeInstanceOf(Page::class)
        ->type->name->toBe('Blog')
        ->layout->name->toBe('Blog Posts');

    get($blogUrl->full_url)
        ->assertOk()
        ->assertSeeText(__('capell-blog::messages.no_articles_found'));
});

test('article page', function (): void {
    $blogCreator = resolve(BlogCreator::class);

    $siteDomain = SiteDomain::factory()->default()->create();
    $site = $siteDomain->site;

    $blogPage = $blogCreator->createBlogPage($site);
    $articleType = $blogCreator->createArticlePageType();
    $articleLayout = $blogCreator->createArticleLayout();

    $archivesPage = $blogCreator->createArchivesPage($blogPage);
    $blogCreator->createArchivePage($archivesPage);

    $tagsPage = $blogCreator->createTagsPage($site, $blogPage, createWidgets: true);
    $tagPage = $blogCreator->createTagPage($site, $tagsPage);

    $article = Article::factory()
        ->site($siteDomain->site)
        ->layout($articleLayout)
        ->type($articleType)
        ->withTranslations($site->languages)
        ->create();

    expect($article)
        ->toBeInstanceOf(Article::class)
        ->type->name->toBe('Article')
        ->layout->name->toBe('Article');

    get(blogTestPageUrl($article->pageUrl)->full_url)
        ->assertOk()
        ->assertSeeText((string) $article->title)
        ->assertSeeText((string) $blogPage->label);
});

test('article page list tags', function (): void {
    $blogCreator = resolve(BlogCreator::class);

    $language = Language::factory()->create();
    $site = Site::factory()->recycle($language)->withTranslations()->create();
    $tags = Tag::factory()->count(3)->translate($language)->type(TagTypeEnum::Page)->create();

    $blogPage = $blogCreator->createBlogPage($site);

    $tagsPage = $blogCreator->createTagsPage($site, $blogPage, createWidgets: true);
    $tagPage = $blogCreator->createTagPage($site, $tagsPage);

    $archivesPage = $blogCreator->createArchivesPage($blogPage);
    $archivePage = $blogCreator->createArchivePage($archivesPage);
    $archivePageUrl = blogTestPageUrl($archivePage->pageUrl);

    $articleType = $blogCreator->createArticlePageType();
    $articleLayout = $blogCreator->createArticleLayout();

    $article = Article::factory()
        ->site($site)
        ->layout($articleLayout)
        ->type($articleType)
        ->withTranslations()
        ->hasAttached($tags)
        ->create();

    $archiveUrl = GenerateArchiveUrlAction::run(
        $archivePageUrl,
        ArchiveMonthData::fromDate(
            ($article->visible_from ?? $article->created_at) instanceof CarbonImmutable
                ? ($article->visible_from ?? $article->created_at)
                : CarbonImmutable::instance($article->visible_from ?? $article->created_at),
        ),
    );

    expect($article)
        ->toBeInstanceOf(Article::class)
        ->type->name->toBe('Article')
        ->layout->name->toBe('Article')
        ->translation->slug->toBe(str($article->name . '-' . $article->translation->language->locale)->slug()->toString())
        ->pageUrl->url->toBe('/blog/' . $article->translation->slug)
        ->tags->toHaveCount(3);

    get(blogTestPageUrl($article->pageUrl)->full_url)
        ->assertOk()
        ->assertSeeText((string) $article->title)
        ->assertSeeText((string) $blogPage->label)
        ->assertSee($tags[0]->translate('name', $language->code))
        ->assertElementExists('a[href="' . $tags[0]->getUrl($tagPage, $language) . '"]')
        ->assertElementExists('a[href="' . $archiveUrl . '"]');
});

test('articles pagination', function (): void {
    $blogCreator = resolve(BlogCreator::class);

    $siteDomain = SiteDomain::factory()->default()->create();
    $site = $siteDomain->site;

    $blogPage = $blogCreator->createBlogPage($site, meta: ['limit' => 5]);
    $blogUrl = blogTestPageUrl($blogPage->pageUrl);

    $articleType = $blogCreator->createArticlePageType();
    $articleLayout = $blogCreator->createArticleLayout();

    $articles = Article::factory()
        ->count(12)
        ->site($siteDomain->site)
        ->layout($articleLayout)
        ->type($articleType)
        ->withTranslations($site->languages)
        ->sequence(fn ($sequence): array => ['visible_from' => CarbonImmutable::now()->subDays($sequence->index)])
        ->create();

    $orderedArticles = Article::query()
        ->with('translation')
        ->whereKey($articles->pluck('id'))
        ->publishedLatest()
        ->get();

    $this->shownArticles = 0;

    expect($blogPage)
        ->toBeInstanceOf(Page::class)
        ->type->name->toBe('Blog')
        ->layout->name->toBe('Blog Posts');

    get($blogUrl->full_url)
        ->assertOk()
        ->assertElementExists(
            'title',
            fn (AssertElement $elm): BaseAssert => $elm->containsText(blogTestTranslation($blogPage->translation)->title . ' | ' . $site->title),
        )
        ->assertElementExists(
            'h1',
            fn (AssertElement $elm): BaseAssert => $elm->containsText((string) blogTestTranslation($blogPage->translation)->title),
        )
        ->assertElementExists(
            '.results',
            fn (AssertElement $elm): BaseAssert => $elm->contains('.asset-item', count: 5)
                ->each(
                    '.asset-item',
                    function (AssertElement $elm) use ($orderedArticles): BaseAssert {
                        $this->shownArticles++;
                        $article = blogTestArticle($orderedArticles->get($this->shownArticles - 1));

                        return $elm->containsText((string) $article->title);
                    },
                ),
        )
        ->assertElementExists(
            '.pagination',
            fn (AssertElement $elm): BaseAssert => $elm->find(
                '.pagination-info',
                fn (AssertElement $elm): BaseAssert => $elm->has('aria-label', 'Showing 1 to 5 of 12 results'),
            )
                ->contains('.wire-pagination-links a', count: 3)
                ->find(
                    '.wire-pagination-links a[rel="next"]',
                    fn (AssertElement $elm): BaseAssert => $elm
                        ->has('href', $blogUrl->full_url . '/page/2')
                        ->has('wire:navigate'),
                ),
        );

    get($blogUrl->full_url . '/page/2')
        ->assertOk()
        ->assertElementExists(
            '.results',
            fn (AssertElement $elm): BaseAssert => $elm->contains('.asset-item', count: 5)
                ->each(
                    '.asset-item',
                    function (AssertElement $elm) use ($orderedArticles): BaseAssert {
                        $this->shownArticles++;
                        $article = blogTestArticle($orderedArticles->get($this->shownArticles - 1));

                        return $elm->containsText((string) $article->title);
                    },
                ),
        )
        ->assertElementExists(
            '.pagination',
            fn (AssertElement $elm): BaseAssert => $elm->find(
                '.pagination-info',
                fn (AssertElement $elm): BaseAssert => $elm->has('aria-label', 'Showing 6 to 10 of 12 results'),
            )
                ->contains('.wire-pagination-links a', count: 4)
                ->find(
                    '.wire-pagination-links a[rel="prev"]',
                    fn (AssertElement $elm): BaseAssert => $elm
                        ->has('href', $blogUrl->full_url)
                        ->has('wire:navigate'),
                )
                ->find(
                    '.wire-pagination-links a[rel="next"]',
                    fn (AssertElement $elm): BaseAssert => $elm
                        ->has('href', $blogUrl->full_url . '/page/3')
                        ->has('wire:navigate'),
                ),
        );

    // End the assertion chain before starting the next request
    get($blogUrl->full_url . '/page/3')
        ->assertOk()
        ->assertElementExists(
            '.results',
            fn (AssertElement $elm): BaseAssert => $elm->contains('.asset-item', count: 2)
                ->each(
                    '.asset-item',
                    function (AssertElement $elm) use ($orderedArticles): BaseAssert {
                        $this->shownArticles++;
                        $article = blogTestArticle($orderedArticles->get($this->shownArticles - 1));

                        return $elm->containsText((string) $article->title);
                    },
                ),
        )
        ->assertElementExists(
            '.pagination',
            fn (AssertElement $elm): BaseAssert => $elm->find(
                '.pagination-info',
                fn (AssertElement $elm): BaseAssert => $elm->has('aria-label', 'Showing 11 to 12 of 12 results'),
            )
                ->contains('.wire-pagination-links a', count: 3)
                ->find(
                    '.wire-pagination-links a[rel="prev"]',
                    fn (AssertElement $elm): BaseAssert => $elm
                        ->has('href', $blogUrl->full_url . '/page/2')
                        ->has('wire:navigate'),
                )
                ->doesntContain('.wire-pagination-links a[rel="next"]'),
        );
});

test('articles pagination clamps invalid requested page values to the first page', function (string $requestedPage): void {
    $blogCreator = resolve(BlogCreator::class);

    $siteDomain = SiteDomain::factory()->default()->create();
    $site = $siteDomain->site;

    $blogPage = $blogCreator->createBlogPage($site, meta: ['limit' => 2]);
    $blogUrl = blogTestPageUrl($blogPage->pageUrl);

    $articleType = $blogCreator->createArticlePageType();
    $articleLayout = $blogCreator->createArticleLayout();

    Article::factory()
        ->site($siteDomain->site)
        ->layout($articleLayout)
        ->type($articleType)
        ->withTranslations($site->languages)
        ->forEachSequence(
            ['visible_from' => '2023-01-01'],
            ['visible_from' => '2023-02-01'],
            ['visible_from' => '2023-03-01'],
        )
        ->create();

    $orderedArticles = Article::query()
        ->with('translation')
        ->whereRelation('site', 'id', $site->getKey())
        ->publishedLatest()
        ->get();

    $firstArticle = blogTestArticle($orderedArticles->get(0));
    $secondArticle = blogTestArticle($orderedArticles->get(1));
    $thirdArticle = blogTestArticle($orderedArticles->get(2));

    get($blogUrl->full_url . '?pageQuery=' . urlencode($requestedPage))
        ->assertOk()
        ->assertElementExists(
            '.results',
            fn (AssertElement $elm): BaseAssert => $elm
                ->contains('.asset-item', count: 2)
                ->containsText((string) blogTestTranslation($firstArticle->translation)->title)
                ->containsText((string) blogTestTranslation($secondArticle->translation)->title)
                ->doesntContainText((string) blogTestTranslation($thirdArticle->translation)->title),
        )
        ->assertElementExists(
            '.pagination',
            fn (AssertElement $elm): BaseAssert => $elm->find(
                '.pagination-info',
                fn (AssertElement $elm): BaseAssert => $elm->has('aria-label', 'Showing 1 to 2 of 3 results'),
            ),
        );
})->with(['0', '-2', 'not-a-page']);
