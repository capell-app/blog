<?php

declare(strict_types=1);

use Capell\Blog\Actions\SeedBlogScreenshotFixtureAction;
use Capell\Blog\Models\Article;
use Capell\Core\Models\SiteDomain;
use Capell\LayoutBuilder\Actions\InstallPackageAction;
use Capell\Tests\Support\Concerns\CreatesAdminUser;
use Capell\Tests\Support\ScreenshotManifest;

uses(CreatesAdminUser::class);

beforeEach(function (): void {
    $this->actingAsAdmin();
    putenv('CAPELL_SCREENSHOT_FIXTURE=record-state');
    putenv('CAPELL_SCREENSHOT_APP_PATH=' . base_path());
});

afterEach(function (): void {
    putenv('CAPELL_SCREENSHOT_FIXTURE');
    putenv('CAPELL_SCREENSHOT_APP_PATH');
});

it('opens the populated article index, actual article form and public blog', function (): void {
    InstallPackageAction::run();
    $domain = SiteDomain::factory()->default()->create();
    SeedBlogScreenshotFixtureAction::run();
    $article = Article::query()->where('name', 'Planning a content calendar that survives launch week')->sole();
    $article->update(['visible_from' => now()->addYear(), 'visible_until' => now()->subDay()]);
    SeedBlogScreenshotFixtureAction::run();
    $freshArticle = $article->fresh();
    throw_unless($freshArticle instanceof Article, RuntimeException::class, 'The seeded Blog screenshot article could not be refreshed.');
    throw_unless($freshArticle->visible_from !== null, RuntimeException::class, 'The seeded Blog screenshot article has no visible_from timestamp.');
    expect($freshArticle->visible_from->isPast())->toBeTrue()->and($freshArticle->visible_until)->toBeNull();
    $this->get(blogSuppressedCaptureUrl('articles-admin-index'))->assertOk()->assertSee('Planning a content calendar');
    $this->get(blogSuppressedCaptureUrl('create-edit-article-form'))->assertOk()->assertSee('tags')->assertSee('name');
    $this->get(blogSuppressedCaptureUrl('blog-admin-sidebar-menu-open'))->assertOk()->assertSee('Planning a content calendar')->assertSee('fi-topbar-open-collapse-sidebar-btn', false);
    auth()->logout();
    $this->get('http://' . $domain->domain . blogSuppressedCaptureUrl('blog-page-frontend-output'))->assertOk()->assertSee('Planning a content calendar');
});

function blogSuppressedCaptureUrl(string $key): string
{
    return ScreenshotManifest::captureUrl(__DIR__ . '/../../docs/screenshots.json', $key);
}
