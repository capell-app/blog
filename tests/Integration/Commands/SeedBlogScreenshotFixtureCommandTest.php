<?php

declare(strict_types=1);

use Capell\Blog\Actions\SeedBlogScreenshotFixtureAction;
use Capell\Core\Models\SiteDomain;
use Capell\LayoutBuilder\Actions\InstallPackageAction as LayoutBuilderInstallPackageAction;

function withBlogScreenshotFixtureEnvironment(Closure $callback): void
{
    putenv('CAPELL_SCREENSHOT_FIXTURE=record-state');
    putenv('CAPELL_SCREENSHOT_APP_PATH=' . base_path());

    try {
        $callback();
    } finally {
        putenv('CAPELL_SCREENSHOT_FIXTURE');
        putenv('CAPELL_SCREENSHOT_APP_PATH');
    }
}

it('seeds published screenshot articles idempotently', function (): void {
    LayoutBuilderInstallPackageAction::run();
    SiteDomain::factory()->default()->create();

    withBlogScreenshotFixtureEnvironment(function (): void {
        expect(SeedBlogScreenshotFixtureAction::run())->toBe(4)
            ->and(SeedBlogScreenshotFixtureAction::run())->toBe(4);
    });
});

it('refuses to seed outside the disposable screenshot environment', function (): void {
    expect(fn (): int => SeedBlogScreenshotFixtureAction::run())
        ->toThrow(RuntimeException::class, 'explicit disposable local screenshot environment');
});

it('requires --force on the blog screenshot fixture command', function (): void {
    $this->artisan('capell:blog-screenshot-fixture')->assertFailed();
});
