<?php

declare(strict_types=1);

use Capell\Blog\Actions\EnsureBlogPublishingSurfaceAction;
use Capell\Blog\Actions\GenerateArchiveUrlAction;
use Capell\Blog\Data\ArchiveMonthData;
use Capell\Blog\Data\BlogPublishingSurfaceRequestData;
use Capell\Blog\Models\Article;
use Capell\Blog\Support\BlogFrontendRuntimeManifestContributor;
use Capell\Blog\Support\Creator\BlogCreator;
use Capell\Core\Enums\MediaCollectionEnum;
use Capell\Core\Models\Language;
use Capell\Core\Models\Site;
use Capell\Core\Models\SiteDomain;
use Capell\Frontend\Data\FrontendContext;
use Capell\Frontend\Data\FrontendRuntimeManifestData;
use Capell\Frontend\Enums\CacheEnum;
use Capell\Frontend\Enums\RenderingStrategyEnum;
use Capell\Frontend\Events\FrontendContextResolved;
use Capell\Frontend\Support\State\FrontendState;
use Capell\Tags\Enums\TagTypeEnum;
use Capell\Tags\Models\Tag;
use Capell\Tests\Fixtures\Models\User;
use Capell\Tests\Support\Concerns\TestingFrontend;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Database\Eloquent\Factories\Sequence;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;

use function Pest\Laravel\get;
use function PHPUnit\Framework\assertInstanceOf;
use function PHPUnit\Framework\assertIsArray;

uses(TestingFrontend::class);

beforeEach(function (): void {
    config(['capell.disable_cache_save_keys' => [CacheEnum::Pages->value . '-*']]);
});

test('rich article route stays inside the public query budget', function (): void {
    $fixture = blogRichRouteQueryBudgetFixture(articleCount: 8);

    $queryCount = blogMeasurePublicRouteQueries($fixture['article_url']);

    // Localized public media metadata and port-aware site-domain resolution
    // add bounded hydration to the page, site logo, and article image paths.
    expect($queryCount)->toBeLessThanOrEqual(148);
});

test('rich blog archive and tag routes stay inside the public query budget', function (string $routeKey, int $budget): void {
    $fixture = blogRichRouteQueryBudgetFixture(articleCount: 8);

    $queryCount = blogMeasurePublicRouteQueries($fixture[$routeKey]);

    expect($queryCount)->toBeLessThanOrEqual($budget);
})->with([
    'blog index' => ['blog_url', 115],
    // Includes the cache-safe public widget snapshot lookup, port-aware
    // domain resolution, and bounded pre-render footer hydration required by
    // layout-native archive pages.
    'archive month' => ['archive_url', 134],
    'tag result' => ['tag_url', 148],
]);

test('Foundation hands prepared values to the Blog request before runtime hydration', function (): void {
    $fixture = blogRichRouteQueryBudgetFixture(articleCount: 3);
    $checked = false;
    Event::listen(FrontendContextResolved::class, function (FrontendContextResolved $event) use (&$checked): void {
        $state = resolve(FrontendState::class);
        $prepared = $event->context->getFrontendData();
        assertIsArray($prepared);
        expect($state->page())->toBe($event->context->page());
        expect($state->site())->toBe($event->context->site());
        expect($state->language())->toBe($event->context->language());
        expect($state->getFrontendData())
            ->toHaveKey('foundation.footer.contact_page', $prepared['foundation.footer.contact_page'])
            ->toHaveKey('foundation.page.ancestors', $prepared['foundation.page.ancestors'])
            ->not->toHaveKey('foundation.page.home');
        $checked = true;
    });

    blogMeasurePublicRouteQueries($fixture['blog_url']);

    expect($checked)->toBeTrue();
})->group('blog-prepared-handoff');

test('Blog preserves prepared null and empty values and hydrates absent values', function (string $mode): void {
    $fixture = blogRichRouteQueryBudgetFixture(articleCount: 3);
    blogMeasurePublicRouteQueries($fixture['blog_url']);
    $state = resolve(FrontendState::class);
    $context = new FrontendContext(
        site: $state->site(),
        language: $state->language(),
        page: $state->page(),
        layout: $state->layout(),
        theme: $state->theme(),
        params: [],
        slug: null,
    );
    $ancestors = $mode === 'empty' ? new EloquentCollection : null;
    if ($mode !== 'absent') {
        $context->setFrontendData('foundation.footer.contact_page', null);
        $context->setFrontendData('foundation.page.ancestors', $ancestors);
    }

    DB::flushQueryLog();
    DB::enableQueryLog();

    try {
        resolve(BlogFrontendRuntimeManifestContributor::class)->contribute(
            $context,
            FrontendRuntimeManifestData::forRenderingStrategy(RenderingStrategyEnum::BladeOnly),
        );
        $queries = collect(DB::getQueryLog());
    } finally {
        DB::disableQueryLog();
    }

    expect($context->getFrontendData())->toHaveKey('foundation.footer.contact_page', null)
        ->toHaveKey('foundation.page.ancestors', $ancestors);
    expect($queries->filter(fn (array $query): bool => in_array('contact', $query['bindings'], true)))->toHaveCount($mode === 'absent' ? 1 : 0);
    expect($queries->filter(fn (array $query): bool => str_contains($query['query'], 'between') && str_contains($query['query'], '_lft')))->toHaveCount($mode === 'absent' ? 1 : 0);
})->with(['null', 'empty', 'absent'])->group('blog-prepared-handoff');

/**
 * @return array{
 *     article_url: string,
 *     blog_url: string,
 *     archive_url: string,
 *     tag_url: string,
 * }
 */
function blogRichRouteQueryBudgetFixture(int $articleCount): array
{
    $blogCreator = resolve(BlogCreator::class);

    $language = Language::factory()->english()->create();
    $site = Site::factory()->recycle($language)->withTranslations()->create(['language_id' => $language->id]);
    SiteDomain::factory()
        ->default()
        ->site($site)
        ->language($language)
        ->create([
            'domain' => 'example.com',
            'path' => null,
            'scheme' => null,
        ]);

    $surface = EnsureBlogPublishingSurfaceAction::run(
        new BlogPublishingSurfaceRequestData(site: $site),
    );
    $surface->blogPage->mergeMeta(['limit' => 6]);
    $surface->blogPage->save();

    $articleType = $blogCreator->createArticlePageType();
    $articleLayout = $blogCreator->createArticleLayout();
    $author = User::factory()->create(['bio' => 'Writes useful publishing notes.']);
    $tags = Tag::factory()
        ->count(3)
        ->translate($language)
        ->type(TagTypeEnum::Page)
        ->site($site)
        ->create();

    /** @var EloquentCollection<int, Article> $articles */
    $articles = Article::factory()
        ->count($articleCount)
        ->site($site)
        ->layout($articleLayout)
        ->type($articleType)
        ->withTranslations($site->languages)
        ->state(['created_by' => $author->getKey()])
        ->sequence(fn (Sequence $sequence): array => [
            'visible_from' => now()->subDays($articleCount - $sequence->index),
        ])
        ->create();

    $articles->each(function (Article $article) use ($tags): void {
        $article->tags()->attach($tags->pluck('id')->all());
        $article
            ->addMedia(__DIR__ . '/../../Fixtures/test-image.jpg')
            ->preservingOriginal()
            ->toMediaCollection(MediaCollectionEnum::Image->value);
    });

    /** @var Article $article */
    $article = $articles->sortByDesc('visible_from')->values()->get(2);
    /** @var Tag $tag */
    $tag = $tags->firstOrFail();
    $publishedAt = $article->visible_from ?? $article->created_at;
    assertInstanceOf(DateTimeInterface::class, $publishedAt);
    $publishedAt = CarbonImmutable::instance($publishedAt);
    $archiveDate = ArchiveMonthData::fromDate($publishedAt);

    return [
        'article_url' => blogTestPageUrl($article->pageUrl)->full_url,
        'blog_url' => blogTestPageUrl($surface->blogPage->pageUrl)->full_url,
        'archive_url' => GenerateArchiveUrlAction::run(
            blogTestPageUrl($surface->archivePage->pageUrl),
            $archiveDate,
        ),
        'tag_url' => $tag->getUrl($surface->tagPage, $language),
    ];
}

function blogMeasurePublicRouteQueries(string $url): int
{
    // Fixture saves queue graph/cache maintenance through Laravel's deferred
    // callback queue. Drain that setup work before measuring the public route.
    defer()->invoke();

    DB::flushQueryLog();
    DB::enableQueryLog();

    get($url)
        ->assertOk()
        ->assertDontSee('frontend-authoring', false)
        ->assertDontSee('data-authoring', false)
        ->assertDontSee('data-editable', false)
        ->assertDontSee('signed-editor-url', false);

    $queryCount = count(DB::getQueryLog());
    DB::disableQueryLog();

    return $queryCount;
}
