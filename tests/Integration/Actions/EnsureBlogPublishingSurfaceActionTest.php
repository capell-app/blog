<?php

declare(strict_types=1);

use Capell\Blog\Actions\AttachBlogPublishingSurfaceToNavigationAction;
use Capell\Blog\Actions\EnsureArticlePublishingDefaultsAction;
use Capell\Blog\Actions\EnsureBlogPublishingSurfaceAction;
use Capell\Blog\Data\BlogPublishingSurfaceData;
use Capell\Blog\Data\BlogPublishingSurfaceRequestData;
use Capell\Blog\Data\BlogPublishingSurfaceResultData;
use Capell\Blog\Enums\BlogLayoutEnum;
use Capell\Blog\Enums\BlogPageTypeEnum;
use Capell\Core\Actions\SetupPageUrlsAction;
use Capell\Core\Enums\LayoutEnum;
use Capell\Core\Facades\CapellCore;
use Capell\Core\Models\Blueprint;
use Capell\Core\Models\Layout;
use Capell\Core\Models\Page;
use Capell\Core\Models\PageUrl;
use Capell\Core\Models\Site;
use Capell\Core\Models\Translation;
use Capell\LayoutBuilder\Actions\InstallPackageAction as LayoutBuilderInstallPackageAction;
use Capell\Navigation\Enums\NavigationHandle;
use Capell\Navigation\Enums\NavigationItemType;
use Capell\Navigation\Models\Navigation;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Schema;

beforeEach(function (): void {
    LayoutBuilderInstallPackageAction::run();
    EnsureArticlePublishingDefaultsAction::run();
});

it('creates the blog publishing surface with translations and urls', function (): void {
    $site = Site::factory()->withTranslations()->create();

    $surface = EnsureBlogPublishingSurfaceAction::run(
        new BlogPublishingSurfaceRequestData(site: $site),
    );

    expect($surface)->toBeInstanceOf(BlogPublishingSurfaceData::class)
        ->and($surface->blogPage)->toBeInstanceOf(Page::class)
        ->and($surface->archivesPage->parent_id)->toBe($surface->blogPage->id)
        ->and($surface->archivePage->parent_id)->toBe($surface->archivesPage->id)
        ->and($surface->tagsPage->parent_id)->toBe($surface->blogPage->id)
        ->and($surface->tagPage->parent_id)->toBe($surface->tagsPage->id)
        ->and($surface->authorPage->parent_id)->toBe($surface->blogPage->id);

    $siteLanguages = $site->languages()->pluck('languages.id');

    foreach ([
        $surface->blogPage,
        $surface->archivesPage,
        $surface->archivePage,
        $surface->tagsPage,
        $surface->tagPage,
        $surface->authorPage,
    ] as $page) {
        expect($page->translations()->whereIn('language_id', $siteLanguages)->count())->toBe($siteLanguages->count())
            ->and($page->pageUrls()->whereIn('language_id', $siteLanguages)->count())->toBe($siteLanguages->count());
    }
});

it('rolls back the complete surface when downstream provisioning fails', function (): void {
    $site = Site::factory()->withTranslations()->create();

    AttachBlogPublishingSurfaceToNavigationAction::shouldRun()
        ->once()
        ->andThrow(new RuntimeException('Navigation provisioning failed.'));

    expect(fn (): BlogPublishingSurfaceResultData => EnsureBlogPublishingSurfaceAction::run(
        new BlogPublishingSurfaceRequestData(site: $site),
    ))->toThrow(RuntimeException::class, 'Navigation provisioning failed.');

    expect(Page::query()->where('site_id', $site->id)->count())->toBe(0);
});

it('retains the site argument as a compatibility adapter', function (): void {
    $site = Site::factory()->withTranslations()->create();

    $surface = EnsureBlogPublishingSurfaceAction::run($site);

    expect($surface->blogPage->site_id)->toBe($site->id);
});

it('creates the blog publishing surface when navigation is disabled despite its table being available', function (): void {
    expect(Schema::hasTable('navigations'))->toBeTrue();

    CapellCore::forcePackageInstalled('capell-app/navigation', false);
    $site = Site::factory()->withTranslations()->create();
    $language = $site->languages()->firstOrFail();
    $navigation = Navigation::factory()->site($site)->language($language)->create([
        'key' => NavigationHandle::Main->value,
    ]);

    $surface = EnsureBlogPublishingSurfaceAction::run($site);

    expect($surface->blogPage)->toBeInstanceOf(Page::class)
        ->and($surface->archivesPage)->toBeInstanceOf(Page::class)
        ->and($surface->archivePage)->toBeInstanceOf(Page::class)
        ->and($surface->tagsPage)->toBeInstanceOf(Page::class)
        ->and($surface->tagPage)->toBeInstanceOf(Page::class)
        ->and($surface->authorPage->site_id)->toBe($site->id)
        ->and($navigation->refresh()->items->toCollection())->toBeEmpty();
});

it('creates blog archive and tag pages with the expected urls', function (): void {
    $site = Site::factory()->withTranslations()->create();

    $surface = EnsureBlogPublishingSurfaceAction::run($site);

    expect($surface->blogPage->pageUrls()->pluck('url')->all())->toContain('/blog')
        ->and($surface->archivesPage->pageUrls()->pluck('url')->all())->toContain('/blog/archives')
        ->and($surface->archivePage->pageUrls()->pluck('url')->all())->toContain('/blog/archives/*')
        ->and($surface->tagsPage->pageUrls()->pluck('url')->all())->toContain('/blog/tags')
        ->and($surface->tagPage->pageUrls()->pluck('url')->all())->toContain('/blog/tags/*')
        ->and($surface->authorPage->pageUrls()->pluck('url')->all())->toContain('/blog/author/*');
});

it('links the blog page into main and footer navigation', function (): void {
    $site = Site::factory()->withTranslations()->create();
    $language = $site->languages()->firstOrFail();

    $mainNavigation = Navigation::factory()->site($site)->language($language)->create([
        'key' => NavigationHandle::Main->value,
    ]);
    $footerNavigation = Navigation::factory()->site($site)->language($language)->create([
        'key' => NavigationHandle::Footer->value,
    ]);

    $surface = EnsureBlogPublishingSurfaceAction::run($site);

    $mainNavigation->refresh();
    $footerNavigation->refresh();

    expect($mainNavigation->items->toCollection()->pluck('data.pageable_id'))->toContain($surface->blogPage->id)
        ->and($mainNavigation->items->toCollection()->pluck('type'))->toContain(NavigationItemType::Page)
        ->and($footerNavigation->items->toCollection()->pluck('data.pageable_id'))->toContain($surface->blogPage->id)
        ->and($footerNavigation->items->toCollection()->pluck('type'))->toContain(NavigationItemType::Page);
});

it('is idempotent for a site', function (): void {
    $site = Site::factory()->withTranslations()->create();

    EnsureBlogPublishingSurfaceAction::run($site);
    EnsureBlogPublishingSurfaceAction::run($site);

    expect(Page::query()
        ->where('site_id', $site->id)
        ->whereHas('blueprint', fn (Builder $query): Builder => $query->where('key', BlogPageTypeEnum::Blog->value))
        ->count())->toBe(1)
        ->and(Page::query()
            ->where('site_id', $site->id)
            ->whereHas('blueprint', fn (Builder $query): Builder => $query->where('key', BlogPageTypeEnum::Archive->value))
            ->count())->toBe(1)
        ->and(Page::query()
            ->where('site_id', $site->id)
            ->whereHas('blueprint', fn (Builder $query): Builder => $query->where('key', BlogPageTypeEnum::Tag->value))
            ->count())->toBe(1)
        ->and(Page::query()
            ->where('site_id', $site->id)
            ->whereHas('blueprint', fn (Builder $query): Builder => $query->where('key', BlogPageTypeEnum::Author->value))
            ->count())->toBe(1);
});

it('adds the author archive page to an already provisioned blog surface', function (): void {
    $site = Site::factory()->withTranslations()->create();

    $surface = EnsureBlogPublishingSurfaceAction::run($site);

    $surface->authorPage->translations()->delete();
    $surface->authorPage->pageUrls()->delete();
    $surface->authorPage->forceDelete();

    expect(Page::query()
        ->where('site_id', $site->id)
        ->whereHas('blueprint', fn (Builder $query): Builder => $query->where('key', BlogPageTypeEnum::Author->value))
        ->count())->toBe(0);

    $reprovisioned = EnsureBlogPublishingSurfaceAction::run($site);

    expect($reprovisioned->authorPage->pageUrls()->pluck('url')->all())->toContain('/blog/author/*')
        ->and(Page::query()
            ->where('site_id', $site->id)
            ->whereHas('blueprint', fn (Builder $query): Builder => $query->where('key', BlogPageTypeEnum::Author->value))
            ->count())->toBe(1);
});

it('adopts an existing blog url page instead of creating a duplicate blog page', function (): void {
    $site = Site::factory()->withTranslations()->create();
    $language = $site->languages()->firstOrFail();
    $genericType = Blueprint::factory()->page()->default()->create();
    $genericLayout = Layout::factory()->create(['key' => 'generic-blog']);

    $existingPage = Page::factory()
        ->site($site)
        ->layout($genericLayout)
        ->type($genericType)
        ->create(['name' => 'Existing Blog']);

    Translation::factory()
        ->translatable($existingPage)
        ->language($language)
        ->slug('blog')
        ->create(['title' => 'Existing Blog']);

    SetupPageUrlsAction::run($existingPage);

    $surface = EnsureBlogPublishingSurfaceAction::run($site);

    expect($surface->blogPage->is($existingPage))->toBeTrue()
        ->and($surface->blogPage->refresh()->blueprint?->key)->toBe(BlogPageTypeEnum::Blog->value)
        ->and(PageUrl::query()
            ->where('site_id', $site->id)
            ->where('language_id', $language->id)
            ->where('url', '/blog')
            ->count())->toBe(1);
});

it('updates existing tag pages to the tag results layout without duplicating them', function (): void {
    $site = Site::factory()->withTranslations()->create();

    $surface = EnsureBlogPublishingSurfaceAction::run($site);
    $legacyResultsLayout = Layout::query()->where('key', LayoutEnum::Results->value)->firstOrFail();

    $surface->tagPage->forceFill(['layout_id' => $legacyResultsLayout->id])->save();

    $updatedSurface = EnsureBlogPublishingSurfaceAction::run($site);
    $tagResultsLayout = Layout::query()->where('key', BlogLayoutEnum::TagResults->value)->firstOrFail();

    expect(Page::query()
        ->where('site_id', $site->id)
        ->whereHas('blueprint', fn (Builder $query): Builder => $query->where('key', BlogPageTypeEnum::Tag->value))
        ->count())->toBe(1)
        ->and($updatedSurface->tagPage->is($surface->tagPage))->toBeTrue()
        ->and($updatedSurface->tagPage->layout_id)->toBe($tagResultsLayout->id);
});
