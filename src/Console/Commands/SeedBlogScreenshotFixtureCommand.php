<?php

declare(strict_types=1);

namespace Capell\Blog\Console\Commands;

use Capell\Blog\Actions\SeedBlogScreenshotFixtureAction;
use Illuminate\Console\Command;
use Throwable;

final class SeedBlogScreenshotFixtureCommand extends Command
{
    protected $signature = 'capell:blog-screenshot-fixture {--force : Confirm an intentional disposable screenshot seed}';

    protected $description = 'Seed Blog record state for an explicit disposable screenshot run';

    public function handle(): int
    {
        if (! $this->option('force')) {
            $this->error('Refusing to seed screenshot fixtures without --force.');

            return self::FAILURE;
        }

        try {
            $count = SeedBlogScreenshotFixtureAction::run();
        } catch (Throwable $exception) {
            $this->error($exception->getMessage());

            return self::FAILURE;
        }

        $this->info(sprintf('Blog screenshot fixture initialized with %d articles.', $count));

        return self::SUCCESS;
    }
}
