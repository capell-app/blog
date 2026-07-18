<?php

declare(strict_types=1);

use Capell\Blog\Filament\Configurators\Articles\ArticlePageConfigurator;
use Capell\Blog\Filament\Configurators\Widgets\ArticleWidgetConfigurator;
use Capell\Blog\Filament\Configurators\Widgets\RelatedWidgetConfigurator;
use Capell\Blog\Filament\Resources\Articles\ArticleResource;
use Capell\Blog\Health\BlogHealthCheck;
use Capell\Blog\Manifest\BlogAdminResourcesContribution;
use Capell\Blog\Manifest\BlogConfiguratorsContribution;
use Capell\Blog\Manifest\BlogConsoleCommandsContribution;
use Capell\Blog\Manifest\BlogFrontendComponentsContribution;
use Capell\Blog\Manifest\BlogMigrationsContribution;
use Capell\Blog\Manifest\BlogModelsContribution;
use Capell\Blog\Manifest\BlogPageTypesContribution;
use Capell\Blog\Manifest\BlogPermissionsContribution;
use Capell\Blog\Manifest\BlogRenderHooksContribution;
use Capell\Blog\Manifest\BlogRoutesContribution;
use Capell\Blog\Models\Article;
use Capell\Core\Contracts\Extensions\ChecksExtensionHealth;
use Capell\Core\Contracts\Extensions\ExtensionContribution;
use Capell\Core\Contracts\Extensions\RegistersExtensionAdminResource;
use Capell\Core\Contracts\Extensions\RegistersExtensionFrontendComponent;
use Capell\Core\Contracts\Extensions\RegistersExtensionPageType;
use Capell\Core\Contracts\Extensions\RegistersExtensionPermission;
use Capell\Core\Contracts\Extensions\RegistersExtensionRenderHook;
use Capell\Core\Contracts\Extensions\RegistersExtensionRoute;
use Capell\Core\Contracts\Extensions\RunsExtensionMigration;
use Capell\Core\Support\Manifest\ManifestValidator;
use Capell\Tags\Filament\Resources\Tags\TagResource;
use Capell\Tags\Models\Tag;
use Illuminate\Support\Facades\File;

describe('blog capell.json manifest', function (): void {
    $blogManifest = fn (): array => json_decode(
        File::get(__DIR__ . '/../../capell.json'),
        associative: true,
    );

    $blogComposerManifest = fn (): array => json_decode(
        File::get(__DIR__ . '/../../composer.json'),
        associative: true,
    );

    it('declares requires using full composer package names', function () use ($blogManifest): void {
        $manifest = $blogManifest();

        $requires = $manifest['dependencies']['requires'] ?? [];

        foreach ($requires as $requirement) {
            expect($requirement)->toContain('/');
        }
    });

    it('requires capell-app/core as a dependency', function () use ($blogManifest): void {
        $manifest = $blogManifest();

        expect($manifest['dependencies']['requires'])->toContain('capell-app/core');
    });

    it('requires the layout-builder package for article widgets and layout defaults', function () use ($blogManifest, $blogComposerManifest): void {
        $manifest = $blogManifest();
        $composerManifest = $blogComposerManifest();

        expect($manifest['dependencies']['requires'])
            ->toContain('capell-app/layout-builder')
            ->and($composerManifest['require'])
            ->toHaveKey('capell-app/layout-builder');
    });

    it('is sold as a premium proprietary publishing package', function () use ($blogManifest, $blogComposerManifest): void {
        $manifest = $blogManifest();
        $composerManifest = $blogComposerManifest();

        expect($manifest['product']['group'])->toBe('Capell Publishing')
            ->and($manifest['product']['tier'])->toBe('premium')
            ->and($manifest['product']['bundle'])->toBe('publishing')
            ->and($manifest['commercial']['proposedLicense'])->toBe('paid')
            ->and($manifest['commercial']['supportPolicy'])->toBe('priority')
            ->and($composerManifest['license'])->toBe('proprietary');
    });

    it('does not require premium packages', function () use ($blogManifest, $blogComposerManifest): void {
        $manifest = $blogManifest();
        $composerManifest = $blogComposerManifest();

        $packagesPath = realpath(__DIR__ . '/../../../');

        expect($packagesPath)->not->toBeFalse();

        $premiumPackageNames = collect(File::directories((string) $packagesPath))
            ->map(function (string $packagePath): ?string {
                $manifestPath = $packagePath . '/capell.json';

                if (! File::exists($manifestPath)) {
                    return null;
                }

                $packageManifest = json_decode(File::get($manifestPath), associative: true, flags: JSON_THROW_ON_ERROR);

                if (($packageManifest['product']['tier'] ?? null) !== 'premium') {
                    return null;
                }

                return is_string($packageManifest['name'] ?? null) ? $packageManifest['name'] : null;
            })
            ->filter()
            ->values();

        expect($manifest['dependencies']['requires'])
            ->not->toContain(...$premiumPackageNames->all());

        foreach ($premiumPackageNames as $premiumPackageName) {
            expect($composerManifest['require'])->not->toHaveKey($premiumPackageName);
        }
    });

    it('keeps premium integrations as optional bridges', function () use ($blogManifest): void {
        $manifest = $blogManifest();

        expect($manifest['dependencies']['supports'])
            ->toContain('capell-app/comments')
            ->toContain('capell-app/insights')
            ->toContain('capell-app/publishing-studio')
            ->toContain('capell-app/site-discovery');
    });

    it('declares blog capabilities permissions and cache invalidation sources', function () use ($blogManifest): void {
        $manifest = $blogManifest();
        $invalidationSourceData = $manifest['performance']['cacheSafety']['invalidationSources'] ?? [];

        throw_unless(is_array($invalidationSourceData), RuntimeException::class, 'Blog invalidation sources must be an array.');

        $invalidationSources = collect($invalidationSourceData);

        expect($manifest['capabilities'])
            ->toContain('blog-articles')
            ->toContain('blog-cache-invalidation')
            ->and($manifest['permissions'])
            ->toContain('article.view')
            ->toContain('tag.view')
            ->and($invalidationSources->pluck('model')->all())
            ->toContain(Article::class)
            ->toContain(Tag::class);
    });

    it('promotes committed admin screenshots to the marketplace manifest', function () use ($blogManifest): void {
        $manifest = $blogManifest();
        $screenshots = $manifest['marketplace']['screenshots'] ?? [];

        throw_unless(is_array($screenshots), RuntimeException::class, 'Blog marketplace screenshots must be an array.');

        expect(collect($screenshots)->pluck('path')->all())
            ->toContain('docs/screenshots/articles-admin-index.png')
            ->toContain('docs/screenshots/articles-admin-index-dark.png')
            ->toContain('docs/screenshots/create-edit-article-form.png')
            ->toContain('docs/screenshots/create-edit-article-form-dark.png');
    });

    it('registers the full blog demo command', function () use ($blogManifest): void {
        $manifest = $blogManifest();

        expect($manifest['commands']['demo'])->toBe('capell:blog-demo')
            ->and($manifest['commands']['demoParams'])->toBe(['sites', 'languages', 'force']);
    });

    it('declares a single package health contribution for diagnostics', function () use ($blogManifest): void {
        $manifest = $blogManifest();
        $healthChecks = $manifest['healthChecks'] ?? [];

        throw_unless(is_array($healthChecks), RuntimeException::class, 'Blog health checks must be an array.');
        throw_unless(is_array($healthChecks[0] ?? null), RuntimeException::class, 'Blog health check entry must be an array.');

        expect($healthChecks)->toHaveCount(1)
            ->and($healthChecks[0]['key'])->toBe('blog.package-health')
            ->and($healthChecks[0]['class'])->toBe(BlogHealthCheck::class)
            ->and(collect($healthChecks)->pluck('class')->duplicates()->all())->toBe([]);
    });

    it('declares shipped extension contribution surfaces', function () use ($blogManifest, $blogComposerManifest): void {
        $packagePath = dirname(__DIR__, 2);
        $manifest = $blogManifest();
        $composer = $blogComposerManifest();
        $contributions = $manifest['contributes'] ?? null;

        throw_unless(is_array($contributions), RuntimeException::class, 'Expected Blog manifest contributions.');

        (new ManifestValidator)->validate($manifest, $composer, 'capell-app/blog', $packagePath . '/capell.json');

        expect(collect($contributions))
            ->toContain([
                'type' => 'admin-resource',
                'class' => BlogAdminResourcesContribution::class,
                'resourceClasses' => [
                    ArticleResource::class,
                    TagResource::class,
                ],
                'groups' => ['Page', 'Tag'],
            ])
            ->toContain([
                'type' => 'configurator',
                'class' => BlogConfiguratorsContribution::class,
                'configuratorClasses' => [
                    ArticlePageConfigurator::class,
                    ArticleWidgetConfigurator::class,
                    RelatedWidgetConfigurator::class,
                ],
                'groups' => ['Page', 'Widget'],
            ])
            ->toContain([
                'type' => 'model',
                'class' => BlogModelsContribution::class,
                'modelClass' => Article::class,
            ])
            ->toContain([
                'type' => 'page-type',
                'class' => BlogPageTypesContribution::class,
                'name' => 'article',
                'modelClass' => Article::class,
            ])
            ->toContain([
                'type' => 'page-variation',
                'class' => BlogPageTypesContribution::class,
                'name' => 'article',
                'modelClass' => Article::class,
                'resourceName' => 'article',
            ])
            ->and(collect($contributions)->pluck('type')->all())->toBe([
                'admin-resource',
                'configurator',
                'model',
                'permission',
                'page-type',
                'page-variation',
                'frontend-component',
                'render-hook',
                'route',
                'migration',
                'console-command',
                'health-check',
            ])
            ->and(data_get(collect($contributions)->firstWhere('type', 'permission'), 'permissions'))->toBe($manifest['permissions'])
            ->and(data_get(collect($contributions)->firstWhere('type', 'migration'), 'tables'))->toBe($manifest['database']['requiredTables'])
            ->and($manifest['contributionTraceability']['deferredContributions'])->toBe([])
            ->and(class_implements(BlogAdminResourcesContribution::class))->toContain(RegistersExtensionAdminResource::class)
            ->and(class_implements(BlogConfiguratorsContribution::class))->toContain(ExtensionContribution::class)
            ->and(class_implements(BlogModelsContribution::class))->toContain(ExtensionContribution::class)
            ->and(class_implements(BlogPageTypesContribution::class))->toContain(RegistersExtensionPageType::class)
            ->and(class_implements(BlogPermissionsContribution::class))->toContain(RegistersExtensionPermission::class)
            ->and(class_implements(BlogFrontendComponentsContribution::class))->toContain(RegistersExtensionFrontendComponent::class)
            ->and(class_implements(BlogRenderHooksContribution::class))->toContain(RegistersExtensionRenderHook::class)
            ->and(class_implements(BlogRoutesContribution::class))->toContain(RegistersExtensionRoute::class)
            ->and(class_implements(BlogMigrationsContribution::class))->toContain(RunsExtensionMigration::class)
            ->and(class_implements(BlogConsoleCommandsContribution::class))->toContain(ExtensionContribution::class)
            ->and(class_implements(BlogHealthCheck::class))->toContain(ChecksExtensionHealth::class);
    });
});
