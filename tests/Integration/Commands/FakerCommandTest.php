<?php

declare(strict_types=1);

use Capell\Blog\Models\Article;
use Capell\Core\Models\SiteDomain;
use Illuminate\Console\Command;

describe('capell:blog-faker command', function (): void {
    it('requires a positive count', function (): void {
        $this->artisan('capell:blog-faker', [
            '--count' => 0,
        ])
            ->expectsOutput('The --count option must be at least 1.')
            ->assertExitCode(Command::FAILURE);

        expect(Article::query()->count())->toBe(0);
    });

    it('skips seeding when no sites exist', function (): void {
        $this->artisan('capell:blog-faker', [
            '--count' => 2,
        ])
            ->expectsOutput('No sites found. Skipping.')
            ->assertExitCode(Command::SUCCESS);

        expect(Article::query()->count())->toBe(0);
    });

    it('seeds articles with example image source URLs', function (): void {
        SiteDomain::factory()->default()->create();

        $this->artisan('capell:blog-faker', [
            '--count' => 3,
        ])
            ->expectsOutput('Generating 3 fake blog articles across 1 site.')
            ->expectsOutputToContain('[1/1] Creating 3 blog articles')
            ->expectsOutput('Backfilling example images for 3 articles.')
            ->expectsOutputToContain('Total fake articles created: 3')
            ->assertExitCode(Command::SUCCESS);

        expect(Article::query()->count())->toBe(3)
            ->and(Article::query()->get()->map(fn (Article $article): mixed => $article->getMeta('image_source.url')))
            ->each->toStartWith('https://images.unsplash.com/');
    });
});
