<?php

declare(strict_types=1);
use Symfony\Component\Finder\Finder;

it('declares navigation as an explicit package dependency', function (): void {
    $packagePath = dirname(__DIR__, 2);
    $capellManifestContents = file_get_contents($packagePath . '/capell.json');
    $composerManifestContents = file_get_contents($packagePath . '/composer.json');

    $capellManifest = json_decode(
        $capellManifestContents === false ? '[]' : $capellManifestContents,
        associative: true,
        flags: JSON_THROW_ON_ERROR,
    );
    $composerManifest = json_decode(
        $composerManifestContents === false ? '[]' : $composerManifestContents,
        associative: true,
        flags: JSON_THROW_ON_ERROR,
    );

    expect($capellManifest['dependencies']['requires'])->toContain('capell-app/navigation')
        ->and($composerManifest['require'])->toHaveKey('capell-app/navigation');
});

it('declares site discovery as an optional package bridge', function (): void {
    $packagePath = dirname(__DIR__, 2);
    $capellManifestContents = file_get_contents($packagePath . '/capell.json');
    $composerManifestContents = file_get_contents($packagePath . '/composer.json');

    $capellManifest = json_decode(
        $capellManifestContents === false ? '[]' : $capellManifestContents,
        associative: true,
        flags: JSON_THROW_ON_ERROR,
    );
    $composerManifest = json_decode(
        $composerManifestContents === false ? '[]' : $composerManifestContents,
        associative: true,
        flags: JSON_THROW_ON_ERROR,
    );

    expect($capellManifest['dependencies']['requires'])->not->toContain('capell-app/site-discovery')
        ->and($capellManifest['dependencies']['supports'])->toContain('capell-app/site-discovery')
        ->and($composerManifest['require'])->not->toHaveKey('capell-app/site-discovery');
});

it('keeps blog package references inside the blog source package except intentional bridges', function (): void {
    $rootPath = dirname(__DIR__, 4);
    $intentionalBridgePaths = [
        'packages/comments/src/Actions/RegisterDefaultCommentablesAction.php',
    ];
    $violations = [];

    $files = (new Finder)
        ->files()
        ->in($rootPath . '/packages')
        ->path('/\/src\//')
        ->name('*.php')
        ->contains('Capell\\Blog');

    foreach ($files as $file) {
        $relativePath = str_replace($rootPath . '/', '', $file->getPathname());

        if (str_starts_with($relativePath, 'packages/blog/src/')) {
            continue;
        }

        if (in_array($relativePath, $intentionalBridgePaths, true)) {
            continue;
        }

        $violations[] = $relativePath;
    }

    expect($violations)->toBeEmpty();
});

arch()
    ->expect('Capell\Blog')
    ->classes()
    ->toUseStrictEquality();

arch('blog package does not depend on seo-suite')
    ->expect('Capell\Blog')
    ->not->toUse('Capell\SeoSuite');

arch('blog package does not depend on comments')
    ->expect('Capell\Blog')
    ->not->toUse('Capell\Comments');

arch('blog model uses its optional publishing studio bridge')
    ->expect('Capell\Blog\Models\Article')
    ->not->toUse('Capell\PublishingStudio\BelongsToWorkspace')
    ->toUse('Capell\Blog\Support\PublishingStudio\Concerns\BelongsToOptionalWorkspace');
