<?php

declare(strict_types=1);

namespace Capell\Blog\Tests;

use Capell\Admin\Facades\CapellAdmin;
use Capell\Admin\Providers\AdminServiceProvider;
use Capell\Admin\Providers\Filament\AdminPanelProvider;
use Capell\Analytics\Providers\AnalyticsServiceProvider;
use Capell\Blog\Providers\AdminServiceProvider as BlogAdminServiceProvider;
use Capell\Blog\Providers\BlogServiceProvider;
use Capell\Blog\Providers\ConsoleServiceProvider as BlogConsoleServiceProvider;
use Capell\Blog\Providers\FrontendServiceProvider as BlogFrontendServiceProvider;
use Capell\Core\Facades\CapellCore;
use Capell\Core\Models\Media;
use Capell\DefaultTheme\Providers\DefaultThemeServiceProvider;
use Capell\Frontend\Contracts\SettingsMigrationProviderInterface;
use Capell\Frontend\Providers\FrontendServiceProvider;
use Capell\Mosaic\Providers\MosaicServiceProvider;
use Capell\Tags\Models\Tag;
use Capell\Tags\Providers\TagsServiceProvider;
use Capell\Tests\AbstractTestCase;
use Illuminate\Contracts\Config\Repository;
use Illuminate\Foundation\Application;
use Illuminate\Support\Facades\Blade;
use Livewire\LivewireServiceProvider;
use Override;
use Spatie\ImageOptimizer\Optimizers\Svgo;

class BlogTestCase extends AbstractTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Blade::anonymousComponentPath(__DIR__ . '/../../default-theme/resources/views/components', 'capell');

        $this->registerAndMigrateSettings(
            CapellCore::getSettingMigrations(),
            __DIR__ . '/../../../vendor/capell-app/core/database/settings',
        );

        $this->registerAndMigrateSettings(
            CapellAdmin::getSettingMigrations(),
            __DIR__ . '/../../../vendor/capell-app/admin/database/settings',
        );

        $this->registerAndMigrateSettings(
            resolve(SettingsMigrationProviderInterface::class)->getSettingMigrations(),
            __DIR__ . '/../../../vendor/capell-app/frontend/database/settings',
        );
    }

    protected function getPackageServiceName(): string
    {
        return 'capell-blog';
    }

    /**
     * @param  Application  $app
     * @return class-string[]
     */
    protected function getPackageProviders(mixed $app): array
    {
        return [
            ...parent::getPackageProviders($app),
            MosaicServiceProvider::class,
            AdminServiceProvider::class,
            AnalyticsServiceProvider::class,
            FrontendServiceProvider::class,
            BlogServiceProvider::class,
            BlogAdminServiceProvider::class,
            BlogConsoleServiceProvider::class,
            BlogFrontendServiceProvider::class,
            DefaultThemeServiceProvider::class,
            TagsServiceProvider::class,
            AdminPanelProvider::class,
            LivewireServiceProvider::class,
        ];
    }

    /**
     * @param  Application  $app
     */
    #[Override]
    protected function getEnvironmentSetUp(mixed $app): void
    {
        parent::getEnvironmentSetUp($app);

        CapellCore::forcePackageInstalled(AdminServiceProvider::$packageName);
        CapellCore::registerPackage(
            AnalyticsServiceProvider::$packageName,
            path: realpath(__DIR__ . '/../../analytics'),
        );
        CapellCore::forcePackageInstalled(AnalyticsServiceProvider::$packageName);
        CapellCore::forcePackageInstalled(BlogServiceProvider::$packageName);
        CapellCore::forcePackageInstalled('capell-app/default-theme');
        CapellCore::forcePackageInstalled(FrontendServiceProvider::$packageName);
        CapellCore::forcePackageInstalled(MosaicServiceProvider::$packageName);

        CapellCore::registerPackage(
            TagsServiceProvider::$packageName,
            path: realpath(__DIR__ . '/../../tags'),
        );
        CapellCore::forcePackageInstalled(TagsServiceProvider::$packageName);

        CapellCore::registerPackage('capell-app/navigation', path: realpath(__DIR__ . '/../../navigation'));
        CapellCore::forcePackageInstalled('capell-app/navigation');

        $app->make(Repository::class)->set('tags.tag_model', Tag::class);
        $app->make(Repository::class)->set('media-library.media_model', Media::class);
        $app->make(Repository::class)->set('media-library.image_optimizers', [
            Svgo::class => [],
        ]);
    }
}
