<?php

declare(strict_types=1);

describe('blog capell.json manifest', function (): void {
    $blogManifest = fn (): array => json_decode(
        file_get_contents(__DIR__ . '/../../capell.json'),
        associative: true,
    );

    $blogComposerManifest = fn (): array => json_decode(
        file_get_contents(__DIR__ . '/../../composer.json'),
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

    it('requires the layout-builder package for article blocks and layout defaults', function () use ($blogManifest, $blogComposerManifest): void {
        $manifest = $blogManifest();
        $composerManifest = $blogComposerManifest();

        expect($manifest['dependencies']['requires'])
            ->toContain('capell-app/layout-builder')
            ->and($composerManifest['require'])
            ->toHaveKey('capell-app/layout-builder');
    });

    it('passes install context into the demo command', function () use ($blogManifest): void {
        $manifest = $blogManifest();

        expect($manifest['commands']['demo'])->toBe('capell:blog-demo')
            ->and($manifest['commands']['demoParams'])->toBe(['sites', 'user']);
    });
});
