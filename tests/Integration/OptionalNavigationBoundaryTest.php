<?php

declare(strict_types=1);

use Capell\Blog\Bridges\Navigation\AddBlogPagesToNavigation;
use Capell\Blog\Bridges\Navigation\BlogNavigationBridge;
use Capell\Blog\Support\BlogFrontendRuntimeManifestContributor;
use Capell\Blog\Support\Creator\BlogCreator;
use Capell\Blog\Support\Loader\BlogLoader;
use Capell\Core\Data\Rendering\FrontendRuntimeManifestData;
use Capell\Core\Enums\Rendering\RenderingStrategyEnum;
use Capell\Core\Facades\CapellCore;
use Capell\Core\Models\Language;
use Capell\Core\Models\Page;
use Capell\Core\Models\Site;
use Capell\Core\Models\Theme;
use Capell\Frontend\Data\FrontendContext;
use Capell\Navigation\Events\NavigationCreating;
use Capell\Navigation\Models\Navigation;
use Illuminate\Contracts\Events\Dispatcher;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Schema;

it('does not register the navigation listener when navigation is disabled despite its classes and table being available', function (): void {
    expect(class_exists(NavigationCreating::class))->toBeTrue()
        ->and(Schema::hasTable('navigations'))->toBeTrue();

    CapellCore::forcePackageInstalled('capell-app/navigation', false);
    $events = Mockery::mock(Dispatcher::class);
    $events->shouldNotReceive('listen');
    Event::swap($events);

    BlogNavigationBridge::registerListener();
});

it('does not hydrate navigation relations for the frontend runtime when navigation is disabled despite its model and table being available', function (): void {
    expect(class_exists(Navigation::class))->toBeTrue()
        ->and(Schema::hasTable('navigations'))->toBeTrue();

    CapellCore::forcePackageInstalled('capell-app/navigation', false);
    $language = Language::factory()->english()->create();
    $site = Site::factory()->recycle($language)->withTranslations()->create(['language_id' => $language->getKey()]);
    $page = Page::factory()->site($site)->layout(resolve(BlogCreator::class)->createArchivesLayout())->withTranslations($site->languages)->create();
    $page->load(['layout', 'translation', 'pageUrl']);
    $page->setRelation('blueprint', null);

    $context = new FrontendContext($site, $language, $page, $page->layout, new Theme, [], null);

    resolve(BlogFrontendRuntimeManifestContributor::class)->contribute(
        $context,
        FrontendRuntimeManifestData::forRenderingStrategy(RenderingStrategyEnum::BladeOnly),
    );

    expect($context->getFrontendData())->toHaveKey('blog.archives')
        ->and($site->relationLoaded('navigations'))->toBeFalse();
});

it('does not execute the cached navigation listener when navigation is disabled despite its classes and table being available', function (): void {
    expect(class_exists(NavigationCreating::class))->toBeTrue()
        ->and(Schema::hasTable('navigations'))->toBeTrue();

    CapellCore::forcePackageInstalled('capell-app/navigation', false);

    $site = Site::factory()->withTranslations()->create();
    $page = resolve(BlogCreator::class)->createBlogPage($site);
    $navigation = Navigation::factory()->site($site)->create(['key' => 'main', 'items' => []]);
    $navigation->refresh();

    $before = $navigation->getAttributes();
    expect(BlogLoader::getBlogPage($site))->toBeInstanceOf(Page::class);

    (new AddBlogPagesToNavigation)->handle(new NavigationCreating($navigation, collect()));

    expect($navigation->refresh()->getAttributes())->toBe($before)
        ->and($page->exists)->toBeTrue();
});
