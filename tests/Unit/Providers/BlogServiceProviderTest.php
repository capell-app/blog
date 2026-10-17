<?php

declare(strict_types=1);

use Capell\Blog\Models\Article;
use Capell\Blog\Providers\BlogServiceProvider;
use Capell\Blog\View\Components\Widget\Page\Archives;
use Capell\Blog\View\Components\Widget\Tag\Tags;
use Capell\Core\Models\Language;
use Capell\Core\Models\Page;
use Capell\Core\Models\Site;
use Capell\Core\Models\Theme;
use Capell\Core\Models\Translation;
use Capell\Frontend\Contracts\FrontendContextReader;
use Capell\Frontend\Data\CacheInvalidationRule;
use Capell\Frontend\Data\FrontendContext;
use Capell\Frontend\Facades\Frontend;
use Capell\Frontend\Support\Cache\CacheInvalidationRegistry;
use Capell\LayoutBuilder\Models\Widget;
use Capell\Tags\Models\Tag;
use Composer\InstalledVersions;
use Livewire\Blaze\Config as BlazeConfig;

test('blog widget components are registered for blaze compilation with nested components', function (): void {
    $blazeConfig = resolve(BlazeConfig::class);

    expect($blazeConfig->shouldCompile(__DIR__ . '/../../../resources/views/components/widget/page/article.blade.php'))->toBeTrue()
        ->and($blazeConfig->shouldCompile(__DIR__ . '/../../../resources/views/components/page/published-date.blade.php'))->toBeTrue();
});

test('blog sidebar widgets default to section-level headings', function (mixed $heading, string $expected): void {
    $page = new Page;
    $context = new FrontendContext(new Site, new Language, $page, null, new Theme, [], null);
    app()->instance(FrontendContextReader::class, $context);
    Frontend::clearResolvedInstance(FrontendContextReader::class);
    Frontend::setFrontendData('blog.archive_page', $page);
    Frontend::setFrontendData('blog.archives', collect());
    Frontend::setFrontendData('blog.tag_page', $page);
    Frontend::setFrontendData('blog.sidebar_tags', collect());
    $widget = new Widget(['meta' => ['heading_tag' => $heading]]);
    $widget->setRelation('translation', new Translation(['title' => 'Sidebar heading']));
    $widget->setRelation('blueprint', null);

    foreach ([Archives::class, Tags::class] as $componentClass) {
        $component = new $componentClass([], 'sidebar', 0, (object) ['index' => 0], $widget);
        expect($component->contentData->headingTag)->toBe($expected)
            ->and($component->contentData->title)->toBe('Sidebar heading');
    }
})->with(['unset' => [null, 'h2'], 'invalid' => [42, 'h2'], 'configured' => ['h3', 'h3']]);

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
