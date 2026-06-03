<?php

declare(strict_types=1);

use Capell\Blog\Models\Article;
use Capell\Blog\Support\Creator\BlogCreator;
use Capell\Core\Enums\MediaCollectionEnum;
use Capell\Core\Models\Site;
use Capell\Tags\Enums\TagTypeEnum;
use Capell\Tags\Models\Tag;
use Capell\Tests\Fixtures\Models\User;
use Capell\Tests\Support\Concerns\TestingFrontend;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Model as EloquentModel;

use function Pest\Laravel\get;

use Sinnbeck\DomAssertions\Asserts\AssertElement;
use Sinnbeck\DomAssertions\Asserts\BaseAssert;

uses(TestingFrontend::class);

test('article page with layout', function (): void {
    $site = Site::factory()->withTranslations()->create();
    $language = blogTestLanguage($site->language);
    $user = User::factory()->create();
    $blogCreator = resolve(BlogCreator::class);
    $blogCreator->createTagPage($site);

    $tags = Tag::factory()->count(3)->translate($language)->type(TagTypeEnum::Page)->create();
    $articles = Article::factory()
        ->site($site)
        ->state(['created_by' => $user->id])
        ->withTranslations()
        ->forEachSequence(
            ['visible_from' => now()->subDays(5)],
            ['visible_from' => now()->subDays(3)],
            ['visible_from' => now()->subDays(1)],
        )
        ->create();
    /** @var Article $article */
    $article = $articles->get(1);
    $article->tags()->attach($tags);
    $article
        ->addMedia(__DIR__ . '/../../Fixtures/test-image.jpg')
        ->preservingOriginal()
        ->toMediaCollection(MediaCollectionEnum::Image->value);
    $articleTags = $article->tags()->ordered()->get();
    $articlePageUrl = blogTestPageUrl($article->pageUrl);
    $articleTranslation = blogTestTranslation($article->translation);
    $articleTitle = (string) $articleTranslation->title;
    $visibleFrom = $article->visible_from;
    $previousArticle = blogTestArticle($articles->get(0));
    $nextArticle = blogTestArticle($articles->get(2));

    throw_unless($visibleFrom instanceof CarbonInterface, RuntimeException::class, 'Expected article visible date.');

    get($articlePageUrl->full_url)
        ->assertOk()
        ->assertElementExists(
            'title',
            fn (AssertElement $elm): BaseAssert => $elm->containsText($articleTitle . ' | ' . $site->title),
        )
        ->assertElementExists(
            'h1',
            fn (AssertElement $elm): BaseAssert => $elm->containsText($articleTitle),
        )
        ->assertElementExists(
            'time.published-date',
            fn (AssertElement $elm): BaseAssert => $elm->has('datetime', $visibleFrom->toW3cString()),
        )
        ->assertElementExists(
            '.capell-page-article .breadcrumbs',
            fn (AssertElement $elm): BaseAssert => $elm
                ->containsText(__('capell-blog::generic.blog'))
                ->containsText($articleTitle),
        )
        ->assertElementExists(
            '.capell-page-article figure img',
            fn (AssertElement $elm): BaseAssert => $elm
                ->has('alt', $articleTitle),
        )
        ->assertElementExists(
            '.capell-blog-article-content',
            fn (AssertElement $elm): BaseAssert => $elm->doesntContain('img'),
        )
        ->assertElementExists(
            '.article-meta',
            fn (AssertElement $elm): BaseAssert => $elm->find(
                '.article-tags',
                fn (AssertElement $elm): BaseAssert => $elm->contains('.tag-item', count: 3)
                    ->each(
                        '.tag-item',
                        fn (AssertElement $elm, int $index): BaseAssert => $elm->containsText($articleTags[$index]->translate('name', $language->code)),
                    ),
            ),
        )
        ->assertElementExists(
            '.article-meta .page-author',
            fn (AssertElement $elm): BaseAssert => $elm->containsText($user->name),
        )
        ->assertElementExists(
            '.neighbor-links',
            fn (AssertElement $elm): BaseAssert => $elm
                ->containsText(__('capell-blog::generic.previous_article'))
                ->containsText(__('capell-blog::generic.next_article'))
                ->containsText((string) blogTestTranslation($previousArticle->translation)->title)
                ->containsText((string) blogTestTranslation($nextArticle->translation)->title),
        )
        ->assertElementExists(
            '#layout-container-latest.blog-latest-articles .widget-pages',
            fn (AssertElement $elm): BaseAssert => $elm->contains('.latest-articles-page-item', count: 2),
        )
        ->assertElementExists(fn (AssertElement $body): BaseAssert => $body->doesntContain('.capell-neighbor-links-mobile'));
});

test('article neighbor navigation skips adjacent articles without urls', function (): void {
    $site = Site::factory()->withTranslations()->create();
    $user = User::factory()->create();
    $blogCreator = resolve(BlogCreator::class);
    $blogCreator->createTagPage($site);

    $articles = Article::factory()
        ->site($site)
        ->state(['created_by' => $user->id])
        ->withTranslations()
        ->forEachSequence(
            ['visible_from' => now()->subDays(5)],
            ['visible_from' => now()->subDays(3)],
            ['visible_from' => now()->subDays(1)],
        )
        ->create();

    $previousArticle = blogTestArticle($articles->get(0));
    $currentArticle = blogTestArticle($articles->get(1));
    $nextArticle = blogTestArticle($articles->get(2));
    $previousArticle->pageUrls()->delete();

    get(blogTestPageUrl($currentArticle->pageUrl)->full_url)
        ->assertOk()
        ->assertDontSeeText((string) blogTestTranslation($previousArticle->translation)->title)
        ->assertSeeText((string) blogTestTranslation($nextArticle->translation)->title);
});

test('article page renders without lazy-loading public blade relations', function (): void {
    $site = Site::factory()->withTranslations()->create();
    $user = User::factory()->create();

    resolve(BlogCreator::class)->createTagPage($site);

    $article = Article::factory()
        ->site($site)
        ->state(['created_by' => $user->id])
        ->withTranslations()
        ->create(['visible_from' => now()->subDay()]);
    $articleUrl = blogTestPageUrl($article->pageUrl)->full_url;
    $articleTitle = (string) blogTestTranslation($article->translation)->title;

    $previous = EloquentModel::preventsLazyLoading();
    EloquentModel::preventLazyLoading();

    try {
        get($articleUrl)
            ->assertOk()
            ->assertSeeText($articleTitle);
    } finally {
        EloquentModel::preventLazyLoading($previous);
    }
});
