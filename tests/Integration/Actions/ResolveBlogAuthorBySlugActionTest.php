<?php

declare(strict_types=1);

use Capell\Blog\Actions\ResolveBlogAuthorBySlugAction;
use Capell\Blog\Data\BlogAuthorData;
use Capell\Blog\Models\Article;
use Capell\Core\Models\Language;
use Capell\Core\Models\Site;
use Capell\Tests\Fixtures\Models\User;

/**
 * @param  array<string, mixed>  $attributes
 */
function blogAuthorResolutionArticle(Site $site, User $author, array $attributes = []): Article
{
    $article = Article::factory()
        ->site($site)
        ->withTranslations($site->languages, ['title' => $attributes['title'] ?? 'Authored Article'])
        ->create(['visible_from' => $attributes['visible_from'] ?? '2023-01-01']);

    $article->forceFill(['created_by' => $author->getKey()])->saveQuietly();

    return $article->refresh();
}

it('derives the public slug from the author display name', function (): void {
    expect(BlogAuthorData::slugForName('  Ada Lovelace '))->toBe('ada-lovelace')
        ->and(BlogAuthorData::slugForName(''))->toBe('');
});

it('resolves an author that has published articles on the site', function (): void {
    $language = Language::factory()->create();
    $site = Site::factory()->recycle($language)->withTranslations()->create();
    $author = User::factory()->create(['name' => 'Ada Lovelace']);

    blogAuthorResolutionArticle($site, $author);

    $resolved = ResolveBlogAuthorBySlugAction::run('ada-lovelace', $site, $language);

    expect($resolved)->toBeInstanceOf(BlogAuthorData::class)
        ->and($resolved?->name)->toBe('Ada Lovelace')
        ->and($resolved?->slug)->toBe('ada-lovelace')
        ->and($resolved?->userId)->toBe((int) $author->getKey());
});

it('returns null for an unknown or empty slug', function (): void {
    $language = Language::factory()->create();
    $site = Site::factory()->recycle($language)->withTranslations()->create();
    $author = User::factory()->create(['name' => 'Ada Lovelace']);

    blogAuthorResolutionArticle($site, $author);

    expect(ResolveBlogAuthorBySlugAction::run('grace-hopper', $site, $language))->toBeNull()
        ->and(ResolveBlogAuthorBySlugAction::run('', $site, $language))->toBeNull()
        ->and(ResolveBlogAuthorBySlugAction::run('   ', $site, $language))->toBeNull();
});

it('ignores authors whose only articles are not yet published', function (): void {
    $language = Language::factory()->create();
    $site = Site::factory()->recycle($language)->withTranslations()->create();
    $author = User::factory()->create(['name' => 'Silent Writer']);

    $article = blogAuthorResolutionArticle($site, $author);
    $article->forceFill(['visible_from' => now()->addYear()])->saveQuietly();

    expect(ResolveBlogAuthorBySlugAction::run('silent-writer', $site, $language))->toBeNull();
});

it('breaks a slug collision deterministically on the lowest user id', function (): void {
    $language = Language::factory()->create();
    $site = Site::factory()->recycle($language)->withTranslations()->create();

    $first = User::factory()->create(['name' => 'Ada Lovelace']);
    $second = User::factory()->create(['name' => 'ADA  LOVELACE']);

    blogAuthorResolutionArticle($site, $second);
    blogAuthorResolutionArticle($site, $first);

    $resolved = ResolveBlogAuthorBySlugAction::run('ada-lovelace', $site, $language);

    expect($resolved?->userId)->toBe((int) $first->getKey());
});

it('keeps a stable author slug resolving after the author is renamed', function (): void {
    $language = Language::factory()->create();
    $site = Site::factory()->recycle($language)->withTranslations()->create();
    $author = User::factory()->create(['name' => 'Ada Lovelace']);

    blogAuthorResolutionArticle($site, $author);

    $published = BlogAuthorData::stableSlugFor((int) $author->getKey(), 'Ada Lovelace');
    $author->forceFill(['name' => 'Ada King'])->saveQuietly();

    $resolved = ResolveBlogAuthorBySlugAction::run($published, $site, $language);

    expect($resolved?->userId)->toBe((int) $author->getKey())
        ->and($resolved?->name)->toBe('Ada King')
        ->and($resolved?->stableSlug)->toStartWith('ada-king-')
        ->and($published)->not->toContain((string) $author->getKey() . '-');
});

it('gives two authors with the same name distinct stable slugs that each resolve to their own author', function (): void {
    $language = Language::factory()->create();
    $site = Site::factory()->recycle($language)->withTranslations()->create();
    $first = User::factory()->create(['name' => 'Sam Taylor']);
    $second = User::factory()->create(['name' => 'Sam Taylor']);

    blogAuthorResolutionArticle($site, $first);
    blogAuthorResolutionArticle($site, $second);

    $firstSlug = BlogAuthorData::stableSlugFor((int) $first->getKey(), 'Sam Taylor');
    $secondSlug = BlogAuthorData::stableSlugFor((int) $second->getKey(), 'Sam Taylor');

    expect($firstSlug)->not->toBe($secondSlug)
        ->and(ResolveBlogAuthorBySlugAction::run($firstSlug, $site, $language))->toBeInstanceOf(BlogAuthorData::class)
        ->and(ResolveBlogAuthorBySlugAction::run($secondSlug, $site, $language))->toBeInstanceOf(BlogAuthorData::class);

    $firstResolved = ResolveBlogAuthorBySlugAction::run($firstSlug, $site, $language);
    $secondResolved = ResolveBlogAuthorBySlugAction::run($secondSlug, $site, $language);

    expect($firstResolved?->userId)->toBe((int) $first->getKey())
        ->and($secondResolved?->userId)->toBe((int) $second->getKey());
});

it('does not resolve a stable slug whose token belongs to an author without published articles', function (): void {
    $language = Language::factory()->create();
    $site = Site::factory()->recycle($language)->withTranslations()->create();
    $author = User::factory()->create(['name' => 'Ada Lovelace']);
    $silent = User::factory()->create(['name' => 'Silent Writer']);

    blogAuthorResolutionArticle($site, $author);

    expect(ResolveBlogAuthorBySlugAction::run(BlogAuthorData::stableSlugFor((int) $silent->getKey(), 'Silent Writer'), $site, $language))->toBeNull();
});
