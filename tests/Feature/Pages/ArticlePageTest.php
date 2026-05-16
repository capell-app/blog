<?php

declare(strict_types=1);

use Capell\Blog\Models\Article;
use Capell\Blog\Support\Creator\BlogCreator;
use Capell\Core\Models\Site;
use Capell\Tags\Enums\TagTypeEnum;
use Capell\Tags\Models\Tag;
use Capell\Tests\Fixtures\Models\User;
use Capell\Tests\Support\Concerns\TestingFrontend;
use Illuminate\Database\Eloquent\Model as EloquentModel;

use function Pest\Laravel\get;

use Sinnbeck\DomAssertions\Asserts\AssertElement;
use Sinnbeck\DomAssertions\Asserts\BaseAssert;

uses(TestingFrontend::class);

test('article page with layout', function (): void {
    $site = Site::factory()->withTranslations()->create();
    $language = $site->language;
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
    $articleTags = $article->tags()->ordered()->get();

    get($article->pageUrl->full_url)
        ->assertOk()
        ->assertElementExists(
            'title',
            fn (AssertElement $elm): BaseAssert => $elm->containsText($article->translation->title . ' | ' . $site->title),
        )
        ->assertElementExists(
            'h1',
            fn (AssertElement $elm): BaseAssert => $elm->containsText($article->translation->title),
        )
        ->assertElementExists(
            'time.published-date',
            fn (AssertElement $elm): BaseAssert => $elm->has('datetime', $article->visible_from->toW3cString()),
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
                ->containsText($articles->get(0)->translation->title)
                ->containsText($articles->get(2)->translation->title),
        )
        ->assertElementExists(
            '#layout-container-latest.blog-latest-articles .widget-pages',
            fn (AssertElement $elm): BaseAssert => $elm->contains('.latest-articles-page-item', count: 2),
        )
        ->assertDontSee('capell-neighbor-links-mobile', false);
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

    $articles->get(0)->pageUrls()->delete();

    get($articles->get(1)->pageUrl->full_url)
        ->assertOk()
        ->assertDontSeeText($articles->get(0)->translation->title)
        ->assertSeeText($articles->get(2)->translation->title);
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
    $articleUrl = $article->pageUrl->full_url;
    $articleTitle = $article->translation->title;

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
