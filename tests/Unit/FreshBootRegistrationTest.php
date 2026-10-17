<?php

declare(strict_types=1);

use Capell\Blog\Listeners\AddBlogPagesToNavigation;
use Capell\Navigation\Events\NavigationCreating;
use Capell\Navigation\Models\Navigation;
use Capell\Tests\Support\PackageInstallationTestCase;
use Illuminate\Foundation\Application;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Schema;

it('registers the navigation listener before schema exists and guards dispatch', function (): void {
    PackageInstallationTestCase::assertFreshInstalledBoot('blog', static function (Application $app): void {
        expect(Schema::hasTable('navigations'))->toBeFalse()
            ->and(Event::getRawListeners()[NavigationCreating::class] ?? [])->toBe([AddBlogPagesToNavigation::class]);

        $items = collect();
        Event::dispatch(new NavigationCreating(new Navigation(['key' => 'main']), $items));

        expect($items)->toBeEmpty();
    });
});
