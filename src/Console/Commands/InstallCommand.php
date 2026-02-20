<?php

declare(strict_types=1);

namespace Capell\Blog\Console\Commands;

use Capell\Admin\Actions\AssignPermissionsToRole;
use Capell\Blog\Enums\ResourceEnum;
use Capell\Blog\Support\BlogModelRegistrar;
use Filament\Facades\Filament;
use Illuminate\Console\Command;

class InstallCommand extends Command
{
    protected $description = 'Install blog package';

    protected $signature = 'capell:blog-install';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        BlogModelRegistrar::register();

        Filament::getDefaultPanel()
            ->resources(array_map(fn (ResourceEnum $resourceEnum) => $resourceEnum->value, ResourceEnum::cases()));

        AssignPermissionsToRole::run(resources: ResourceEnum::cases());

        if (! $this->publishMigrations('create_tag_tables', base_path('vendor/spatie/laravel-tags/database/migrations'))) {
            $this->error('Failed to publish create_tag_tables migration.');

            return Command::FAILURE;
        }

        if (! $this->publishMigrations('alter_tags_table', __DIR__ . '/../../../database/migrations')) {
            $this->error('Failed to publish alter_tags_table migration.');

            return Command::FAILURE;
        }

        $this->call('migrate');

        $this->callSilent('filament:assets');

        $this->newLine();
        $this->info('Capell Blog installed successfully.');

        return self::SUCCESS;
    }

    private function publishMigrations(string $migration, string $path): bool
    {
        if (! is_dir($path)) {
            $this->error('Migrations directory does not exist.');

            return false;
        }

        $this->call(
            'capell:publish-migrations',
            [
                '--items' => [$migration],
                '--path' => $path,
            ],
        );

        return true;
    }
}
