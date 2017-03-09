<?php

declare(strict_types=1);

use Capell\Blog\Filament\Resources\Articles\ArticleResource;
use Capell\Blog\Models\Article;
use Capell\Blog\Support\Creator\BlogCreator;
use Capell\Core\Models\Language;
use Capell\Core\Models\Site;
use Capell\Core\Models\SiteDomain;
use Capell\Tests\Support\Concerns\CreatesAdminUser;

use function Pest\Laravel\get;

uses(CreatesAdminUser::class)
    ->group('page', 'article');

test('admin can see page articles', function (): void {
    test()->actingAsAdmin();

    get(ArticleResource::getUrl())
        ->assertOk();
});

test('cannot see page article', function (): void {
    test()->actingAsUser();

    get(ArticleResource::getUrl())
        ->assertForbidden();
});

test('admin can see create article', function (): void {
    test()->actingAsAdmin();
    resolve(BlogCreator::class)->createArticlePageType();

    $language = Language::factory()->default()->create();

    Site::factory()
        ->has(SiteDomain::factory()->state(['language_id' => $language->id]))
        ->default()
        ->create();

    get(ArticleResource::getUrl('create'))
        ->assertOk();
});

test('the create route does not revive a disabled article blueprint', function (): void {
    test()->actingAsAdmin();
    $type = resolve(BlogCreator::class)->createArticlePageType();
    $type->update(['status' => false]);

    get(ArticleResource::getUrl('create', ['type' => $type->key]))->assertNotFound();

    expect($type->refresh()->status)->toBeFalse();
})->group('blog-editorial-access');

test('ordinary users cannot open the article creation journey', function (): void {
    test()->actingAsUser();

    get(ArticleResource::getUrl('create'))->assertForbidden();
})->group('blog-editorial-access');

test('admin can load edit article', function (): void {
    test()->actingAsAdmin();

    $page = Article::factory()->create();

    get(ArticleResource::getUrl('edit', ['record' => $page]))
        ->assertOk();
});
