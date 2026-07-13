<?php

declare(strict_types=1);

use Capell\Blog\Models\Article;
use Capell\Blog\Support\PublishingStudio\Concerns\BelongsToOptionalWorkspace;
use Capell\PublishingStudio\BelongsToWorkspace;
use Symfony\Component\Finder\Finder;

/** @return array<string, mixed> */
function blogManifestSection(mixed $manifest, string $key): array
{
    if (! is_array($manifest) || ! is_array($manifest[$key] ?? null)) {
        return [];
    }

    $section = [];

    foreach ($manifest[$key] as $sectionKey => $value) {
        $section[(string) $sectionKey] = $value;
    }

    return $section;
}

it('declares navigation as an optional package bridge', function (): void {
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

    expect(blogManifestSection(blogManifestSection($capellManifest, 'dependencies'), 'supports'))->toContain('capell-app/navigation')
        ->and(blogManifestSection($composerManifest, 'suggest'))->toHaveKey('capell-app/navigation')
        ->and(blogManifestSection($composerManifest, 'require'))->not->toHaveKey('capell-app/navigation');
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

    $dependencies = blogManifestSection($capellManifest, 'dependencies');

    expect(blogManifestSection($dependencies, 'requires'))->not->toContain('capell-app/site-discovery')
        ->and(blogManifestSection($dependencies, 'supports'))->toContain('capell-app/site-discovery')
        ->and(blogManifestSection($composerManifest, 'require'))->not->toHaveKey('capell-app/site-discovery');
});

it('keeps blog package references inside the blog source package except intentional bridges', function (): void {
    $rootPath = dirname(__DIR__, 4);
    $intentionalBridgePaths = [
        'packages/comments/src/Actions/RegisterDefaultCommentablesAction.php',
        'packages/theme-liquid-glass/src/Enums/WidgetComponentEnum.php',
        'packages/theme-liquid-glass/src/LiquidGlassThemeServiceProvider.php',
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
    ->expect(Article::class)
    ->not->toUse(BelongsToWorkspace::class)
    ->toUse(BelongsToOptionalWorkspace::class);
