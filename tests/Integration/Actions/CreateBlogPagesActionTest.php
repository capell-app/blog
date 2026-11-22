<?php

declare(strict_types=1);

use Capell\Blog\Actions\CreateBlogPagesAction;
use Capell\Blog\Actions\SeedBlogPublishingSurfaceAction;
use Capell\Blog\Enums\BlogPageTypeEnum;
use Capell\Core\Enums\LayoutEnum;
use Capell\Core\Models\Layout;
use Capell\Core\Models\Page;
use Capell\Core\Models\Site;
use Capell\LayoutBuilder\Actions\InstallPackageAction as LayoutBuilderInstallPackageAction;
use Illuminate\Database\Eloquent\Builder;

beforeEach(function (): void {
    LayoutBuilderInstallPackageAction::run();
    SeedBlogPublishingSurfaceAction::run();
});

it('creates the blog page hierarchy for the site', function (): void {
    $site = Site::factory()->withTranslations()->create();

    $surface = CreateBlogPagesAction::run($site);

    expect(Page::query()->where('site_id', $site->id)->whereKey(
        array_map(static fn (Page $page): int => $page->id, $surface->pages()),
    )->count())->toBe(6);

    expect($surface->blogPage->blueprint?->key)->toBe(BlogPageTypeEnum::Blog->value)
        ->and($surface->archivesPage->refresh()->parent_id)->toBe($surface->blogPage->getKey())
        ->and($surface->archivePage->refresh()->parent_id)->toBe($surface->archivesPage->getKey())
        ->and($surface->archivePage->blueprint?->key)->toBe(BlogPageTypeEnum::Archive->value)
        ->and($surface->tagsPage->refresh()->parent_id)->toBe($surface->blogPage->getKey())
        ->and($surface->tagPage->refresh()->parent_id)->toBe($surface->tagsPage->getKey())
        ->and($surface->tagPage->blueprint?->key)->toBe(BlogPageTypeEnum::Tag->value)
        ->and($surface->authorPage->refresh()->parent_id)->toBe($surface->blogPage->getKey());
});

it('adds the archives widget to the results layout sidebar during install', function (): void {
    $resultsLayout = blogTestLayout(Layout::query()->firstWhere('key', LayoutEnum::Results->value));
    $containers = blogTestArray($resultsLayout->getAttribute('containers'));

    expect($containers)->toBeArray();

    $sidebarWidgetKeys = array_column(blogTestContainerWidgets($containers, 'sidebar'), 'widget_key');

    expect($sidebarWidgetKeys)->toContain('archives');
});

it('is safe to run twice for the same site without duplicating the blog root page', function (): void {
    $site = Site::factory()->withTranslations()->create();

    CreateBlogPagesAction::run($site);
    CreateBlogPagesAction::run($site);

    $blogPageCount = Page::query()
        ->where('site_id', $site->id)
        ->whereHas('blueprint', fn (Builder $query): Builder => $query->where('key', BlogPageTypeEnum::Blog->value))
        ->count();

    expect($blogPageCount)->toBe(1);
});
