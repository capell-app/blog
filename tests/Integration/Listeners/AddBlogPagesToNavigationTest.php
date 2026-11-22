<?php

declare(strict_types=1);

use Capell\Blog\Actions\EnsureBlogPublishingSurfaceAction;
use Capell\Blog\Bridges\Navigation\AddBlogPagesToNavigation;
use Capell\Blog\Providers\BlogServiceProvider;
use Capell\Core\Facades\CapellCore;
use Capell\Core\Models\Site;
use Capell\Navigation\Enums\NavigationHandle;
use Capell\Navigation\Enums\NavigationItemType;
use Capell\Navigation\Events\NavigationCreating;
use Capell\Navigation\Models\Navigation;

it('adds the blog page when main or footer navigation is created', function (): void {
    $site = Site::factory()->withTranslations()->create();
    $language = $site->languages()->firstOrFail();
    $surface = EnsureBlogPublishingSurfaceAction::run($site);
    $navigation = Navigation::factory()->site($site)->language($language)->create([
        'key' => NavigationHandle::Main->value,
        'items' => [],
    ]);

    (new AddBlogPagesToNavigation)->handle(new NavigationCreating($navigation, collect()));

    $navigationItems = $navigation->refresh()->items->toCollection();

    expect($navigationItems->pluck('type'))->toContain(NavigationItemType::Page)
        ->and($navigationItems->pluck('data.pageable_id'))->toContain($surface->blogPage->id);
});

it('adds no blog page while Blog is disabled, and adds it once re-enabled', function (): void {
    $site = Site::factory()->withTranslations()->create();
    $language = $site->languages()->firstOrFail();
    $surface = EnsureBlogPublishingSurfaceAction::run($site);
    $navigation = Navigation::factory()->site($site)->language($language)->create([
        'key' => NavigationHandle::Main->value,
        'items' => [],
    ]);

    CapellCore::markPackageDisabled(BlogServiceProvider::$packageName);
    (new AddBlogPagesToNavigation)->handle(new NavigationCreating($navigation, collect()));

    expect($navigation->refresh()->items->toCollection())->toBeEmpty();

    CapellCore::markPackageInstalled(BlogServiceProvider::$packageName);
    (new AddBlogPagesToNavigation)->handle(new NavigationCreating($navigation, collect()));

    expect($navigation->refresh()->items->toCollection()->pluck('data.pageable_id'))->toContain($surface->blogPage->id);
});

it('ignores navigation handles outside the blog navigation targets', function (): void {
    $site = Site::factory()->withTranslations()->create();
    $language = $site->languages()->firstOrFail();
    EnsureBlogPublishingSurfaceAction::run($site);
    $navigation = Navigation::factory()->site($site)->language($language)->create([
        'key' => 'sidebar',
        'items' => [],
    ]);

    (new AddBlogPagesToNavigation)->handle(new NavigationCreating($navigation, collect()));

    expect($navigation->refresh()->items->toCollection())->toBeEmpty();
});
