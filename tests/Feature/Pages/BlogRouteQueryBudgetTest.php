<?php

declare(strict_types=1);

use Capell\Blog\Actions\GenerateArchiveUrlAction;
use Capell\Blog\Data\ArchiveMonthData;
use Capell\Blog\Models\Article;
use Capell\Blog\Support\Creator\BlogCreator;
use Capell\Core\Enums\MediaCollectionEnum;
use Capell\Core\Models\Language;
use Capell\Core\Models\Site;
use Capell\Core\Models\SiteDomain;
use Capell\Frontend\Enums\CacheEnum;
use Capell\Tags\Enums\TagTypeEnum;
use Capell\Tags\Models\Tag;
use Capell\Tests\Fixtures\Models\User;
use Capell\Tests\Support\Concerns\TestingFrontend;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Database\Eloquent\Factories\Sequence;
use Illuminate\Support\Facades\DB;

use function Pest\Laravel\get;

uses(TestingFrontend::class);

beforeEach(function (): void {
    config(['capell-core.disable_cache_save_keys' => [CacheEnum::Pages->value . '-*']]);
});

test('rich article route stays inside the public query budget', function (): void {
    $fixture = blogRichRouteQueryBudgetFixture(articleCount: 8);

    $queryCount = blogMeasurePublicRouteQueries($fixture['article_url']);

    expect($queryCount)->toBeLessThanOrEqual(140);
});

test('rich blog archive and tag routes stay inside the public query budget', function (string $routeKey, int $budget): void {
    $fixture = blogRichRouteQueryBudgetFixture(articleCount: 8);

    $queryCount = blogMeasurePublicRouteQueries($fixture[$routeKey]);

    expect($queryCount)->toBeLessThanOrEqual($budget);
})->with([
    'blog index' => ['blog_url', 110],
    'archive month' => ['archive_url', 115],
    'tag result' => ['tag_url', 135],
]);

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

    $blogPage = $blogCreator->createBlogPage($site, meta: ['limit' => 6]);
    $archivesPage = $blogCreator->createArchivesPage($blogPage);
    $archivePage = $blogCreator->createArchivePage($archivesPage);
    $tagsPage = $blogCreator->createTagsPage($site, $blogPage, createWidgets: true);
    $tagPage = $blogCreator->createTagPage($site, $tagsPage);
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
    $publishedAt = CarbonImmutable::instance($article->visible_from ?? $article->created_at);
    $archiveDate = ArchiveMonthData::fromDate($publishedAt);

    return [
        'article_url' => blogTestPageUrl($article->pageUrl)->full_url,
        'blog_url' => blogTestPageUrl($blogPage->pageUrl)->full_url,
        'archive_url' => GenerateArchiveUrlAction::run(blogTestPageUrl($archivePage->pageUrl), $archiveDate),
        'tag_url' => $tag->getUrl($tagPage, $language),
    ];
}

function blogMeasurePublicRouteQueries(string $url): int
{
    DB::flushQueryLog();
    DB::enableQueryLog();

    get($url)->assertOk();

    $queryCount = count(DB::getQueryLog());
    DB::disableQueryLog();

    return $queryCount;
}
