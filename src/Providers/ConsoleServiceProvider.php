<?php

declare(strict_types=1);

namespace Capell\Blog\Providers;

use Capell\Blog\Console\Commands\CreateBlogPagesCommand;
use Capell\Blog\Console\Commands\DemoCommand;
use Capell\Blog\Console\Commands\FakerCommand;
use Capell\Blog\Console\Commands\HeroDemoCommand;
use Capell\Blog\Console\Commands\InstallCommand;
use Capell\Blog\Console\Commands\SeedBlogScreenshotFixtureCommand;
use Capell\Blog\Console\Commands\SetupCommand;
use Illuminate\Foundation\Application;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\ServiceProvider;
use Override;

final class ConsoleServiceProvider extends ServiceProvider
{
    #[Override]
    public function register(): void
    {
        if (! $this->app->runningInConsole()) {
            return;
        }

        $this->commands([
            CreateBlogPagesCommand::class,
            DemoCommand::class,
            FakerCommand::class,
            HeroDemoCommand::class,
            InstallCommand::class,
            SeedBlogScreenshotFixtureCommand::class,
            SetupCommand::class,
        ]);

        // Composer can add this provider to an already running installer.
        if ($this->app instanceof Application && $this->app->isBooted()) {
            Artisan::registerCommand($this->app->make(DemoCommand::class));
        }
    }
}
