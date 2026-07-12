<?php

declare(strict_types=1);

use Capell\Blog\Actions\EnsureArticlePublishingDefaultsAction;
use Capell\Blog\Enums\BlogLayoutEnum;
use Capell\Blog\Enums\BlogPageTypeEnum;
use Capell\Blog\Models\Article;
use Capell\Blog\Support\Creator\ArticleCreator;
use Capell\Blog\Support\Creator\BlogCreator;
use Capell\Blog\View\Components\Widget\Page\Related;
use Capell\Core\Enums\BlueprintSubjectEnum;
use Capell\Core\Models\Blueprint;
use Capell\Core\Models\Language;
use Capell\Core\Models\Layout;
use Capell\Core\Models\PageUrl;
use Capell\Core\Models\Site;
use Capell\Frontend\Facades\Frontend;
use Capell\Frontend\Support\CapellFrontendContext;
use Capell\Frontend\Support\State\FrontendState;
use Capell\Tags\Enums\TagTypeEnum;
use Capell\Tags\Models\Tag;
use Capell\Tests\Fixtures\Models\User;
use Illuminate\Support\Collection;

it('creates and updates multilingual articles with portable translation metadata and page urls', function (): void {
    EnsureArticlePublishingDefaultsAction::run();

    $english = Language::factory()->english()->create();
    $welsh = Language::factory()->create(['code' => 'cy', 'name' => 'Welsh']);
    $site = Site::factory()
        ->language($english)
        ->withTranslations([$english, $welsh], siteDomainData: ['scheme' => 'https', 'domain' => 'blog.example.test', 'path' => null])
        ->create();
    $user = User::factory()->create();
    $layout = Layout::query()->where('key', BlogLayoutEnum::Article->value)->firstOrFail();
    $type = Blueprint::query()
        ->where('type', BlueprintSubjectEnum::Page->value)
        ->where('key', BlogPageTypeEnum::Article->value)
        ->firstOrFail();

    $creator = new ArticleCreator;
    $article = $creator->createPage([
        'name' => 'Coverage Driven Article',
        'layout_id' => $layout->getKey(),
        'blueprint_id' => $type->getKey(),
        'image_id' => 123,
        'visible_from' => now()->subDay(),
        'user_id' => $user->getKey(),
        'translations' => [
            'en' => [
                'title' => 'Coverage Driven Article',
                'summary' => 'A concise article summary.',
                'content' => '<p>Portable article copy.</p>',
                'link_text' => 'Read the coverage article',
                'slug' => 'coverage-driven-article',
                'meta' => ['description' => 'SEO description'],
            ],
            'cy' => [
                'title' => 'Erthygl profi',
                'summary' => 'Crynodeb byr.',
                'content' => '<p>Cynnwys cludadwy.</p>',
            ],
        ],
    ], $site, new Collection([$english, $welsh]));

    expect($article)->toBeInstanceOf(Article::class)
        ->and($article->site_id)->toBe($site->getKey())
        ->and($article->layout_id)->toBe($layout->getKey())
        ->and($article->blueprint_id)->toBe($type->getKey())
        ->and($article->meta['image_id'] ?? null)->toBe(123);

    $englishTranslation = blogTestTranslation($article->translations()->where('language_id', $english->getKey())->first());
    $welshTranslation = blogTestTranslation($article->translations()->where('language_id', $welsh->getKey())->first());

    expect($englishTranslation->title)->toBe('Coverage Driven Article')
        ->and($englishTranslation->content)->toBe('<p>Portable article copy.</p>')
        ->and($englishTranslation->meta)->toMatchArray([
            'description' => 'SEO description',
            'summary' => 'A concise article summary.',
            'link_text' => 'Read the coverage article',
            'slug' => 'coverage-driven-article',
        ])
        ->and($englishTranslation->getAttribute('created_by'))->toBe($user->getKey());

    expect($welshTranslation->meta)->toMatchArray([
        'summary' => 'Crynodeb byr.',
        'slug' => 'coverage-driven-article',
    ])
        ->and(PageUrl::query()->where('pageable_id', $article->getKey())->where('pageable_type', $article->getMorphClass())->count())->toBe(2);

    $updatedArticle = $creator->createPage([
        'name' => 'Coverage Driven Article',
        'layout_id' => $layout->getKey(),
        'blueprint_id' => $type->getKey(),
        'translations' => [
            'en' => [
                'title' => 'Updated coverage article',
                'summary' => 'Updated summary.',
                'content' => '<p>Updated portable copy.</p>',
            ],
        ],
    ], $site, new Collection([$english]));

    $freshArticle = blogTestArticle($article->fresh('translation'));
    $updatedTranslation = blogTestTranslation($freshArticle->translation);

    expect($updatedArticle->getKey())->toBe($article->getKey())
        ->and($updatedTranslation->title)->toBe('Updated coverage article')
        ->and($updatedTranslation->meta['summary'] ?? null)->toBe('Updated summary.');
});

it('loads related articles for the current tagged article without leaking the current or unrelated article', function (): void {
    EnsureArticlePublishingDefaultsAction::run();

    $site = Site::factory()->withTranslations()->create();
    $language = blogTestLanguage($site->language);
    resolve(BlogCreator::class)->createBlogPage($site);

    $currentArticle = Article::factory()->site($site)->withTranslations($language)->create(['visible_from' => now()->subDays(3)]);
    $relatedArticle = Article::factory()->site($site)->withTranslations($language)->create(['visible_from' => now()->subDays(2)]);
    $unrelatedArticle = Article::factory()->site($site)->withTranslations($language)->create(['visible_from' => now()->subDay()]);
    $tag = Tag::factory()->translate($language)->type(TagTypeEnum::Page)->create();

    $currentArticle->tags()->attach($tag);
    $relatedArticle->tags()->attach($tag);

    $widget = resolve(BlogCreator::class)->relatedArticlesWidget();
    $widget->forceFill([
        'meta' => [
            ...blogTestArray($widget->meta),
            'limit' => 3,
            'exclude_parent' => true,
            'with_date' => true,
            'page_model' => $currentArticle->getMorphClass(),
        ],
    ]);

    Frontend::clearResolvedInstance(CapellFrontendContext::class);
    app()->instance(CapellFrontendContext::class, new CapellFrontendContext(
        (new FrontendState)
            ->withSite($site)
            ->withLanguage($language)
            ->withPage($currentArticle),
    ));

    $component = new Related(
        container: [],
        containerKey: 'main',
        widgetIndex: 0,
        loop: new stdClass,
        widget: $widget,
    );

    $relatedIds = $component->pages?->pluck('id')->all() ?? [];

    expect($relatedIds)->toContain($relatedArticle->getKey())
        ->and($relatedIds)->not->toContain($currentArticle->getKey(), $unrelatedArticle->getKey());
});
