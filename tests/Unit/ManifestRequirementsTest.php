<?php

declare(strict_types=1);
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

        expect($manifest['product']['group'])->toBe('Capell Publishing')
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

    it('registers the full blog demo command', function () use ($blogManifest): void {
        $manifest = $blogManifest();

        expect($manifest['commands']['demo'])->toBe('capell:blog-demo')
            ->and($manifest['commands']['demoParams'])->toBe(['sites', 'languages', 'force']);
    });
});
