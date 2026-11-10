<?php

declare(strict_types=1);

namespace Capell\Blog\Providers;

use Capell\Core\Support\Migration\MigrationRootRegistry;
use Illuminate\Support\ServiceProvider;
use Override;

final class MigrationMetadataServiceProvider extends ServiceProvider
{
    #[Override]
    public function register(): void
    {
        $this->callAfterResolving(MigrationRootRegistry::class, static function (MigrationRootRegistry $registry): void {
            $registry->register('capell-app/blog', __DIR__ . '/../../database/migrations', [
                '2026_05_10_190842_01_create_articles_table' => 'articles',
            ]);
        });
    }
}
