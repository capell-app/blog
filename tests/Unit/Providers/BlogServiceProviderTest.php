<?php

declare(strict_types=1);

use Capell\Blog\Models\Article;
use Capell\Blog\Providers\BlogServiceProvider;
use Capell\Frontend\Data\CacheInvalidationRule;
use Capell\Frontend\Support\Cache\CacheInvalidationRegistry;
use Capell\Tags\Models\Tag;
use Composer\InstalledVersions;
use Livewire\Blaze\Config as BlazeConfig;

test('blog widget components are registered for blaze compilation with nested components', function (): void {
    $blazeConfig = resolve(BlazeConfig::class);

    expect($blazeConfig->shouldCompile(__DIR__ . '/../../../resources/views/components/widget/page/article.blade.php'))->toBeTrue()
        ->and($blazeConfig->shouldCompile(__DIR__ . '/../../../resources/views/components/page/published-date.blade.php'))->toBeTrue();
});

test('blog sidebar widgets default to section-level headings', function (): void {
    $archives = file_get_contents(__DIR__ . '/../../../src/View/Components/Widget/Page/Archives.php');
    $tags = file_get_contents(__DIR__ . '/../../../src/View/Components/Widget/Tag/Tags.php');

    expect($archives)->toContain("is_string(\$configuredHeadingTag) ? \$configuredHeadingTag : 'h2'")
        ->and($tags)->toContain("is_string(\$configuredHeadingTag) ? \$configuredHeadingTag : 'h2'");
});

test('blog registers frontend cache invalidation dependencies for articles and tags', function (): void {
    $registry = resolve(CacheInvalidationRegistry::class);

    $articlePlan = $registry->planForModel(Article::class);
    $tagPlan = $registry->planForModel(Tag::class);

    expect(collect($articlePlan->rules)->contains(
        fn (CacheInvalidationRule $rule): bool => $rule->kind === CacheInvalidationRule::KIND_INVALIDATE_PATTERN,
    ))->toBeTrue()
        ->and(collect($tagPlan->rules)->contains(
            fn (CacheInvalidationRule $rule): bool => $rule->kind === CacheInvalidationRule::KIND_INVALIDATE_PATTERN,
        ))->toBeTrue();
});

test('blog uses the canonical Livewire version boundary', function (): void {
    $originalInstalledVersions = InstalledVersions::getRawData();
    $livewireV3InstalledVersions = $originalInstalledVersions;
    $livewireV3InstalledVersions['versions']['livewire/livewire'] = array_replace(
        $originalInstalledVersions['versions']['livewire/livewire'],
        [
            'pretty_version' => 'v3.6.4',
            'version' => '3.6.4.0',
        ],
    );
    $installedVersionsReflection = new ReflectionClass(InstalledVersions::class);
    $canGetVendors = $installedVersionsReflection->getProperty('canGetVendors');
    $originalCanGetVendors = $canGetVendors->getValue();

    $canGetVendors->setValue(null, false);
    InstalledVersions::reload($livewireV3InstalledVersions);

    try {
        $provider = new BlogServiceProvider(app());
        $method = new ReflectionMethod($provider, 'isLivewireV3');

        expect($method->invoke($provider))->toBeTrue();
    } finally {
        InstalledVersions::reload($originalInstalledVersions);
        $canGetVendors->setValue(null, $originalCanGetVendors);
    }
});
