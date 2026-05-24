<?php

declare(strict_types=1);

namespace Capell\Blog\Providers;

use Capell\Blog\Console\Commands\CreateBlogPagesCommand;
use Capell\Blog\Console\Commands\DemoCommand;
use Capell\Blog\Console\Commands\FakerCommand;
use Capell\Blog\Console\Commands\HeroDemoCommand;
use Capell\Blog\Console\Commands\InstallCommand;
use Capell\Blog\Console\Commands\SetupCommand;
use Illuminate\Support\ServiceProvider;
use Override;

final class ConsoleServiceProvider extends ServiceProvider
{
    #[Override]
    public function register(): void
    {
        $this->commands([
            CreateBlogPagesCommand::class,
            DemoCommand::class,
            FakerCommand::class,
            HeroDemoCommand::class,
            InstallCommand::class,
            SetupCommand::class,
        ]);
    }
}
