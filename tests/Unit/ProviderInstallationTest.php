<?php

declare(strict_types=1);

use Capell\Admin\Support\AdminSurfaceLookup;
use Capell\Blog\Filament\Resources\Articles\ArticleResource;
use Capell\Tests\Support\PackageInstallationTestCase;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Events\Dispatcher;
use Illuminate\Foundation\Application;
use Illuminate\Routing\Route;
use Illuminate\Routing\Router;
use Illuminate\View\Factory;
use Illuminate\View\FileViewFinder;

it('activates installed runtime once after metadata has already booted', function (): void {
    PackageInstallationTestCase::assertInProcessInstallation('blog', function (Application $app, Closure $refresh): void {
        $finder = $app->make(Factory::class)->getFinder();
        throw_unless($finder instanceof FileViewFinder, RuntimeException::class);
        expect(collect($app->make(Router::class)->getRoutes()->getRoutes())->map(fn (Route $route): ?string => $route->getName())->filter()->all())->not->toContain('capell.blog.feed.xml');
        $refresh();
        expect(collect($app->make(Router::class)->getRoutes()->getRoutes())->map(fn (Route $route): ?string => $route->getName())->all())->toContain('capell.blog.feed.xml', 'capell.blog.tag.feed.atom')
            ->and(AdminSurfaceLookup::resource('Page', 'article'))->toBe(ArticleResource::class);

        $schedule = $app->make(Schedule::class);
        $scheduledEvents = $schedule->events();
        $listeners = $app->make(Dispatcher::class)->getRawListeners();
        $views = $finder->getHints();
        $refresh();
        expect($app->make(Dispatcher::class)->getRawListeners())->toBe($listeners)
            ->and($finder->getHints())->toBe($views)
            ->and($schedule->events())->toBe($scheduledEvents);
    });
});
