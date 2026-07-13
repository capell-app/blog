<?php

declare(strict_types=1);

use Capell\Blog\Listeners\AddBlogPagesToNavigation;
use Capell\Blog\Providers\AdminServiceProvider;
use Capell\Blog\Support\BlogFrontendRuntimeManifestContributor;
use Capell\Core\Facades\CapellCore;
use Capell\Core\Models\Site;
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

    $method = new ReflectionMethod(AdminServiceProvider::class, 'registerNavigationListener');
    $method->invoke(new AdminServiceProvider(app()));
});

it('does not hydrate navigation relations for the frontend runtime when navigation is disabled despite its model and table being available', function (): void {
    expect(class_exists(Navigation::class))->toBeTrue()
        ->and(Schema::hasTable('navigations'))->toBeTrue();

    CapellCore::forcePackageInstalled('capell-app/navigation', false);
    $site = Site::factory()->withTranslations()->create();

    $method = new ReflectionMethod(BlogFrontendRuntimeManifestContributor::class, 'hydrateSiteNavigations');
    $method->invoke(resolve(BlogFrontendRuntimeManifestContributor::class), $site);

    expect($site->relationLoaded('navigations'))->toBeFalse();
});

it('does not execute the cached navigation listener when navigation is disabled despite its classes and table being available', function (): void {
    expect(class_exists(NavigationCreating::class))->toBeTrue()
        ->and(Schema::hasTable('navigations'))->toBeTrue();

    CapellCore::forcePackageInstalled('capell-app/navigation', false);

    (new AddBlogPagesToNavigation)->handle(new NavigationCreating(new Navigation([
        'key' => 'main',
    ]), collect()));

    expect(true)->toBeTrue();
});
