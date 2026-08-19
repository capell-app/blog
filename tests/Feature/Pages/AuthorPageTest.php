<?php

declare(strict_types=1);

use Capell\Blog\Actions\GenerateBlogAuthorUrlAction;
use Capell\Blog\Models\Article;
use Capell\Blog\Support\Creator\BlogCreator;
use Capell\Core\Models\Blueprint;
use Capell\Core\Models\Language;
use Capell\Core\Models\Page;
use Capell\Core\Models\Site;
use Capell\Tests\Fixtures\Models\User;
use Capell\Tests\Support\Concerns\TestingFrontend;
use Illuminate\Database\Eloquent\Model as EloquentModel;

use function Pest\Laravel\get;

use Sinnbeck\DomAssertions\Asserts\AssertElement;
use Sinnbeck\DomAssertions\Asserts\BaseAssert;

uses(TestingFrontend::class);

/**
 * @return array{0: Site, 1: Language, 2: Page}
 */
function blogAuthorArchiveSurface(): array
{
    $blogCreator = resolve(BlogCreator::class);

    $language = Language::factory()->create();
    $site = Site::factory()->recycle($language)->withTranslations()->create();

    $blogCreator->createArticleLayout();
    $blogPage = $blogCreator->createBlogPage($site);
    $authorPage = $blogCreator->createAuthorPage($site, $blogPage);

    return [$site, $language, $authorPage];
}

function blogAuthorArchiveUrl(Page $authorPage, string $slug): string
{
    return GenerateBlogAuthorUrlAction::run(blogTestPageUrl($authorPage->pageUrl), $slug);
}

/**
 * @param  array<string, mixed>  $attributes
 */
function blogAuthorArticle(Site $site, User $author, array $attributes = []): Article
{
    $article = Article::factory()
        ->site($site)
        ->withTranslations($site->languages, ['title' => $attributes['title'] ?? 'Authored Article'])
        ->create(['visible_from' => $attributes['visible_from'] ?? '2023-01-01']);

    $article->forceFill(['created_by' => $author->getKey()])->saveQuietly();

    return $article->refresh();
}

test('author archive page is provisioned at the blog author url', function (): void {
    [, , $authorPage] = blogAuthorArchiveSurface();

    $blueprint = $authorPage->blueprint;
    $meta = $blueprint instanceof Blueprint ? ($blueprint->meta ?? []) : [];

    expect($authorPage->pageUrls()->pluck('url')->all())->toContain('/blog/author/*')
        ->and($blueprint?->key)->toBe('author')
        ->and($meta['url_params'] ?? null)->toBe(['author' => 'string'])
        ->and($meta['accessible'] ?? null)->toBe(false)
        ->and($meta['listable'] ?? null)->toBe(false);
});

test('author archive lists only that authors published articles', function (): void {
    [$site, , $authorPage] = blogAuthorArchiveSurface();

    $author = User::factory()->create(['name' => 'Ada Lovelace']);
    $otherAuthor = User::factory()->create(['name' => 'Grace Hopper']);

    blogAuthorArticle($site, $author, ['title' => 'Analytical Engine Notes', 'visible_from' => '2023-01-01']);
    blogAuthorArticle($site, $author, ['title' => 'Bernoulli Numbers', 'visible_from' => '2023-02-01']);
    blogAuthorArticle($site, $otherAuthor, ['title' => 'Compiler Origins', 'visible_from' => '2023-03-01']);

    $future = blogAuthorArticle($site, $author, ['title' => 'Scheduled Draft', 'visible_from' => '2023-04-01']);
    $future->forceFill(['visible_from' => now()->addYear()])->saveQuietly();

    get(blogAuthorArchiveUrl($authorPage, 'ada-lovelace'))
        ->assertOk()
        ->assertElementExists(
            'h1',
            fn (AssertElement $elm): BaseAssert => $elm->containsText('Articles by Ada Lovelace'),
        )
        ->assertElementExists(
            '.results',
            fn (AssertElement $elm): BaseAssert => $elm
                ->doesntContain('.no-results')
                ->containsText('Analytical Engine Notes')
                ->containsText('Bernoulli Numbers')
                ->doesntContainText('Compiler Origins')
                ->doesntContainText('Scheduled Draft'),
        )
        ->assertDontSee(':Author_name');
});

test('author archive never exposes author pii or userstamp internals', function (): void {
    [$site, , $authorPage] = blogAuthorArchiveSurface();

    $author = User::factory()->create([
        'name' => 'Ada Lovelace',
        'email' => 'ada.pii.marker@example.test',
    ]);

    blogAuthorArticle($site, $author, ['title' => 'Analytical Engine Notes']);

    $response = get(blogAuthorArchiveUrl($authorPage, 'ada-lovelace'))->assertOk();

    $html = (string) $response->getContent();

    expect($html)
        ->not->toContain('ada.pii.marker@example.test')
        ->not->toContain('created_by')
        ->not->toContain('userId')
        ->not->toContain('data-authoring')
        ->and($html)->toContain('Ada Lovelace');

    $response
        ->assertDontSee($author->email)
        ->assertDontSee('mailto:');
});

test('author archive returns not found for an unknown slug', function (): void {
    [$site, , $authorPage] = blogAuthorArchiveSurface();

    $author = User::factory()->create(['name' => 'Ada Lovelace']);
    blogAuthorArticle($site, $author, ['title' => 'Analytical Engine Notes']);

    get(blogAuthorArchiveUrl($authorPage, 'nobody-here'))->assertNotFound();
});

test('author archive returns not found for an author without published articles', function (): void {
    [$site, , $authorPage] = blogAuthorArchiveSurface();

    $author = User::factory()->create(['name' => 'Ada Lovelace']);
    $silent = User::factory()->create(['name' => 'Silent Writer']);

    blogAuthorArticle($site, $author, ['title' => 'Analytical Engine Notes']);

    $unpublished = blogAuthorArticle($site, $silent, ['title' => 'Never Published']);
    $unpublished->forceFill(['visible_from' => now()->addYear()])->saveQuietly();

    get(blogAuthorArchiveUrl($authorPage, 'silent-writer'))->assertNotFound();
});

test('author archive returns not found for an author on another site', function (): void {
    [$site, , $authorPage] = blogAuthorArchiveSurface();
    [$otherSite] = blogAuthorArchiveSurface();

    $localAuthor = User::factory()->create(['name' => 'Ada Lovelace']);
    $remoteAuthor = User::factory()->create(['name' => 'Grace Hopper']);

    blogAuthorArticle($site, $localAuthor, ['title' => 'Analytical Engine Notes']);
    blogAuthorArticle($otherSite, $remoteAuthor, ['title' => 'Compiler Origins']);

    get(blogAuthorArchiveUrl($authorPage, 'grace-hopper'))->assertNotFound();
});

test('author archive resolves colliding names to the lowest user id', function (): void {
    [$site, , $authorPage] = blogAuthorArchiveSurface();

    $first = User::factory()->create(['name' => 'Ada Lovelace']);
    $second = User::factory()->create(['name' => 'ADA  LOVELACE']);

    expect($second->getKey())->toBeGreaterThan($first->getKey());

    blogAuthorArticle($site, $first, ['title' => 'First Authored Article']);
    blogAuthorArticle($site, $second, ['title' => 'Second Authored Article']);

    get(blogAuthorArchiveUrl($authorPage, 'ada-lovelace'))
        ->assertOk()
        ->assertElementExists(
            '.results',
            fn (AssertElement $elm): BaseAssert => $elm
                ->containsText('First Authored Article')
                ->doesntContainText('Second Authored Article'),
        );
});

test('author archive renders without lazy loading page relations', function (): void {
    [$site, , $authorPage] = blogAuthorArchiveSurface();

    $author = User::factory()->create(['name' => 'Ada Lovelace']);
    blogAuthorArticle($site, $author, ['title' => 'Analytical Engine Notes']);

    $previous = EloquentModel::preventsLazyLoading();
    EloquentModel::preventLazyLoading();

    try {
        get(blogAuthorArchiveUrl($authorPage, 'ada-lovelace'))
            ->assertOk()
            ->assertSeeText('Analytical Engine Notes');
    } finally {
        EloquentModel::preventLazyLoading($previous);
    }
});
