<?php

declare(strict_types=1);

use Capell\Blog\Health\BlogHealthCheck;
use Capell\Blog\Models\Article;
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

    it('is sold as a premium publishing package', function () use ($blogManifest): void {
        $manifest = $blogManifest();

        expect($manifest['product']['group'])->toBe('Capell Publishing Pro')
            ->and($manifest['product']['tier'])->toBe('premium')
            ->and($manifest['product']['bundle'])->toBe('publishing-pro')
            ->and($manifest['commercial']['proposedLicense'])->toBe('paid');
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
});
