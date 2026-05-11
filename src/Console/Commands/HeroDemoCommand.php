<?php

declare(strict_types=1);

namespace Capell\Blog\Console\Commands;

use Capell\Blog\Actions\CreateBlogHeroDemoContentAction;
use Capell\Core\Models\Site;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;

final class HeroDemoCommand extends Command
{
    protected $signature = 'capell:hero-demo {--sites=}';

    protected $description = 'Create demo hero content for selected blog sites.';

    public function handle(): int
    {
        $sites = $this->resolveSites();

        if ($sites->isEmpty()) {
            $this->error('Unable to find any selected sites.');

            return self::FAILURE;
        }

        foreach ($sites as $site) {
            CreateBlogHeroDemoContentAction::run($site);
            $this->info(sprintf('Demo hero content has been successfully created for site: %s', $site->name));
        }

        $this->info('Hero demo content inserted successfully.');

        return self::SUCCESS;
    }

    /** @return Collection<int, Site> */
    private function resolveSites(): Collection
    {
        $siteNames = $this->parseSiteNames();

        /** @var Collection<int, Site> $sites */
        $sites = Site::query()
            ->when(
                $siteNames !== [],
                fn (Builder $query): Builder => $query->whereIn('name', $siteNames),
            )
            ->get();

        return $sites;
    }

    /** @return list<string> */
    private function parseSiteNames(): array
    {
        $siteOption = $this->option('sites');

        if (is_string($siteOption) && $siteOption !== '') {
            return array_values(array_filter(array_map(
                trim(...),
                explode(',', $siteOption),
            ), static fn (string $siteName): bool => $siteName !== ''));
        }

        if (is_array($siteOption)) {
            return array_values(array_filter(array_map(
                fn (mixed $siteName): string => is_string($siteName) ? trim($siteName) : '',
                $siteOption,
            ), static fn (string $siteName): bool => $siteName !== ''));
        }

        return [];
    }
}
