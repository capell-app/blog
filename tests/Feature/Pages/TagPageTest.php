<?php

declare(strict_types=1);

use Capell\Blog\Models\Article;
use Capell\Blog\Support\Creator\BlogCreator;
use Capell\Core\Models\Language;
use Capell\Core\Models\Site;
use Capell\Tags\Actions\MergeTagsAction;
use Capell\Tags\Enums\TagTypeEnum;
use Capell\Tags\Models\Tag;
use Capell\Tests\Fixtures\Models\User;
use Capell\Tests\Support\Concerns\TestingFrontend;
use Illuminate\Database\Eloquent\Model as EloquentModel;
use Illuminate\Support\Facades\Gate;

use function Pest\Laravel\get;

use Sinnbeck\DomAssertions\Asserts\AssertElement;
use Sinnbeck\DomAssertions\Asserts\BaseAssert;

uses(TestingFrontend::class);

test('tag page list articles by tag', function (): void {
    $blogCreator = resolve(BlogCreator::class);

    $language = Language::factory()->create();
    $site = Site::factory()->recycle($language)->withTranslations()->create();

    $blogPage = $blogCreator->createBlogPage($site);
    $tagsPage = $blogCreator->createTagsPage($site, $blogPage, createWidgets: true);
    $tagPage = $blogCreator->createTagPage($site, $tagsPage);

    $tag = Tag::factory()->translate($language)->type(TagTypeEnum::Page)->create();

    Article::factory()
        ->site($site)
        ->withTranslations()
        ->hasAttached($tag)
        ->forEachSequence(
            ['visible_from' => '2023-01-01'],
            ['visible_from' => '2023-02-01'],
            ['visible_from' => '2023-03-01'],
            ['visible_from' => '2023-04-01'],
            ['visible_from' => '2023-05-01'],
        )
        ->create();

    $articles = Article::query()
        ->with(['translation', 'pageUrl.siteDomain'])
        ->whereRelation('site', 'id', $site->getKey())
        ->publishedLatest()
        ->get();

    $title = trans((string) blogTestTranslation($tagPage->translation)->title, ['tag_name' => $tag->translate('name', $language->code)]);

    $containers = blogTestLayout($tagPage->layout)->getAttribute('containers');
    $containerWidgets = capell_test_collect($containers)->pluck('widgets.*.widget_key')->flatten()->filter()->toArray();

    expect($tagPage)
        ->translation->title->toBe(':Tag_name Articles')
        ->and($containerWidgets)->toContain('breadcrumbs')
        ->and($articles)->toHaveCount(5);

    $response = get($tag->getUrl($tagPage, $language))
        ->assertOk()
        ->assertDontSeeText(':Tag_name Articles')
        ->assertElementExists(
            'title',
            fn (AssertElement $elm): BaseAssert => $elm->containsText($title . ' | ' . $site->title),
        )
        ->assertElementExists(
            'h1',
            fn (AssertElement $elm): BaseAssert => $elm->containsText($title),
        )
        ->assertElementExists(
            '.results',
            fn (AssertElement $elm): BaseAssert => $elm->doesntContain('.no-results')
                ->contains('.asset-index', count: $articles->count())
                ->each(
                    '.asset-index',
                    function (AssertElement $titleElm, int $index) use ($articles): BaseAssert {
                        $article = $articles->get($index);

                        $article = blogTestArticle($article);

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

    $html = $response->getContent();
    if ($html === '') {
        throw new RuntimeException('Expected the tag page response to contain HTML.');
    }

    $document = new DOMDocument;
    $previousLibxmlErrorHandling = libxml_use_internal_errors(true);

    try {
        $document->loadHTML($html);
    } finally {
        libxml_clear_errors();
        libxml_use_internal_errors($previousLibxmlErrorHandling);
    }

    $breadcrumbNodeList = (new DOMXPath($document))
        ->query('//nav[contains(concat(" ", normalize-space(@class), " "), " breadcrumbs ")]');
    $breadcrumbNode = $breadcrumbNodeList === false ? null : $breadcrumbNodeList->item(0);
    $breadcrumbText = $breadcrumbNode instanceof DOMElement ? trim($breadcrumbNode->textContent) : '';

    expect(preg_replace('/\s+/', ' ', $breadcrumbText))
        ->toContain('Blog')
        ->toContain('Tags')
        ->not->toContain($title);

    $headingClasses = capell_test_collect((new DOMXPath($document))->query('//*[self::h1 or self::h2 or self::h3 or self::h4][contains(concat(" ", normalize-space(@class), " "), " not-prose ")]'))
        ->map(fn (DOMElement $heading): string => $heading->getAttribute('class'));

    $headingClasses->each(function (string $class): void {
        expect(preg_match_all('/(?:^|\s)(?:\\S+:)?text-(?:base|lg|xl|2xl|3xl|4xl)(?:\s|$)/', $class))
            ->toBe(1);
    });
});

test('tag page resolves site tag before global tag with same slug', function (): void {
    $blogCreator = resolve(BlogCreator::class);

    $language = Language::factory()->create();
    $site = Site::factory()->recycle($language)->withTranslations()->create();

    $blogPage = $blogCreator->createBlogPage($site);
    $tagsPage = $blogCreator->createTagsPage($site, $blogPage, createWidgets: true);
    $tagPage = $blogCreator->createTagPage($site, $tagsPage);

    $slug = 'shared-topic';

    $globalTag = Tag::factory()
        ->type(TagTypeEnum::Page)
        ->site(null)
        ->create([
            'name' => [$language->code => 'Global Topic'],
            'slug' => [$language->code => $slug],
        ]);

    $siteTag = Tag::factory()
        ->type(TagTypeEnum::Page)
        ->site($site)
        ->create([
            'name' => [$language->code => 'Site Topic'],
            'slug' => [$language->code => $slug],
        ]);

    $globalArticle = Article::factory()
        ->site($site)
        ->withTranslations($site->languages, ['title' => 'Global Tagged Article'])
        ->hasAttached($globalTag)
        ->create(['visible_from' => '2023-01-01']);

    $siteArticle = Article::factory()
        ->site($site)
        ->withTranslations($site->languages, ['title' => 'Site Tagged Article'])
        ->hasAttached($siteTag)
        ->create(['visible_from' => '2023-02-01']);

    get($siteTag->getUrl($tagPage, $language))
        ->assertOk()
        ->assertSeeText('Site Topic Articles')
        ->assertDontSeeText('Global Topic Articles')
        ->assertElementExists(
            '.results',
            fn (AssertElement $widget): BaseAssert => $widget
                ->containsText((string) blogTestTranslation($siteArticle->translation)->title)
                ->doesntContainText((string) blogTestTranslation($globalArticle->translation)->title),
        );
});

test('tag page returns not found when the tag slug is missing', function (): void {
    $blogCreator = resolve(BlogCreator::class);

    $language = Language::factory()->create();
    $site = Site::factory()->recycle($language)->withTranslations()->create();

    $blogPage = $blogCreator->createBlogPage($site);
    $tagsPage = $blogCreator->createTagsPage($site, $blogPage, createWidgets: true);
    $tagPage = $blogCreator->createTagPage($site, $tagsPage);
    $tagPageUrl = blogTestPageUrl($tagPage->pageUrl);

    $missingTagUrl = rtrim($tagPageUrl->full_url, '/*') . '/missing-topic';

    get($missingTagUrl)
        ->assertNotFound();
});

test('redirects a merged tag slug to the canonical tag url', function (): void {
    $blogCreator = resolve(BlogCreator::class);

    $language = Language::factory()->create();
    $site = Site::factory()->recycle($language)->withTranslations()->create();

    $blogPage = $blogCreator->createBlogPage($site);
    $tagsPage = $blogCreator->createTagsPage($site, $blogPage, createWidgets: true);
    $tagPage = $blogCreator->createTagPage($site, $tagsPage);
    $target = Tag::factory()->site($site)->type(TagTypeEnum::Page)->create([
        'name' => [$language->code => 'Canonical Topic'],
        'slug' => [$language->code => 'canonical-topic'],
    ]);
    $source = Tag::factory()->site($site)->type(TagTypeEnum::Page)->create([
        'name' => [$language->code => 'Old Topic'],
        'slug' => [$language->code => 'old-topic'],
    ]);
    $oldUrl = $source->getUrl($tagPage, $language);

    $actor = User::factory()->create();
    Gate::before(static fn (): bool => true);

    MergeTagsAction::run($target, collect([$source]), $actor);

    get($oldUrl)
        ->assertStatus(301)
        ->assertRedirect($target->getUrl($tagPage, $language));
});

test('tag page renders public empty state when tag has no articles', function (): void {
    $blogCreator = resolve(BlogCreator::class);

    $language = Language::factory()->create();
    $site = Site::factory()->recycle($language)->withTranslations()->create();

    $blogPage = $blogCreator->createBlogPage($site);
    $tagsPage = $blogCreator->createTagsPage($site, $blogPage, createWidgets: true);
    $tagPage = $blogCreator->createTagPage($site, $tagsPage);

    $tag = Tag::factory()
        ->translate($language)
        ->type(TagTypeEnum::Page)
        ->site($site)
        ->create([
            'name' => [$language->code => 'Unpublished Topic'],
            'slug' => [$language->code => 'unpublished-topic'],
        ]);

    get($tag->getUrl($tagPage, $language))
        ->assertOk()
        ->assertSeeText('Unpublished Topic Articles')
        ->assertSeeText('No results found.')
        ->assertDontSee(':Tag_name Articles')
        ->assertDontSee('data-authoring')
        ->assertDontSee('signed-editor-url');
});

test('tag page renders results without lazy-loading page translation data', function (): void {
    $blogCreator = resolve(BlogCreator::class);

    $language = Language::factory()->create();
    $site = Site::factory()->recycle($language)->withTranslations()->create();

    $blogPage = $blogCreator->createBlogPage($site);
    $tagsPage = $blogCreator->createTagsPage($site, $blogPage, createWidgets: true);
    $tagPage = $blogCreator->createTagPage($site, $tagsPage);

    $tag = Tag::factory()
        ->translate($language)
        ->type(TagTypeEnum::Page)
        ->site($site)
        ->create();

    $article = Article::factory()
        ->site($site)
        ->withTranslations($site->languages, ['title' => 'Lazy Load Guard Article'])
        ->hasAttached($tag)
        ->create(['visible_from' => '2023-02-01']);

    $url = $tag->getUrl($tagPage, $language);
    $title = trans((string) blogTestTranslation($tagPage->translation)->title, ['tag_name' => $tag->translate('name', $language->code)]);
    $articleTitle = (string) blogTestTranslation($article->translation)->title;

    $previous = EloquentModel::preventsLazyLoading();
    EloquentModel::preventLazyLoading();

    try {
        get($url)
            ->assertOk()
            ->assertSeeText($title)
            ->assertSeeText($articleTitle);
    } finally {
        EloquentModel::preventLazyLoading($previous);
    }
});
