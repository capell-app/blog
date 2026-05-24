<?php

declare(strict_types=1);

namespace Capell\Blog\Console\Commands;

use Illuminate\Console\Command;

final class DemoCommand extends Command
{
    protected $signature = 'capell:blog-demo {--sites=} {--languages=} {--force}';

    protected $description = 'Seed demo blog articles, tags, and hero content.';

    public function handle(): int
    {
        $fakerExitCode = $this->call('capell:blog-faker', array_filter([
            '--sites' => $this->option('sites'),
            '--languages' => $this->option('languages'),
            '--force' => (bool) $this->option('force'),
        ], static fn (mixed $value): bool => $value !== null && $value !== ''));

        if ($fakerExitCode !== self::SUCCESS) {
            return $fakerExitCode;
        }

        return $this->call('capell:hero-demo', array_filter([
            '--sites' => $this->option('sites'),
        ], static fn (mixed $value): bool => $value !== null && $value !== ''));
    }
}
