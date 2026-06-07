<?php

declare(strict_types=1);

use Capell\Blog\Models\Article;
use Capell\Frontend\Data\CacheInvalidationRule;
use Capell\Frontend\Support\Cache\CacheInvalidationRegistry;
use Capell\Tags\Models\Tag;
use Livewire\Blaze\Config as BlazeConfig;

test('blog widget components are registered for blaze compilation with nested components', function (): void {
    $blazeConfig = resolve(BlazeConfig::class);

    expect($blazeConfig->shouldCompile(__DIR__ . '/../../../resources/views/components/widget/page/article.blade.php'))->toBeTrue()
        ->and($blazeConfig->shouldCompile(__DIR__ . '/../../../resources/views/components/page/published-date.blade.php'))->toBeTrue();
});

test('blog registers frontend cache invalidation dependencies for articles and tags', function (): void {
    $registry = resolve(CacheInvalidationRegistry::class);

    $articlePlan = $registry->planForModel(Article::class);
    $tagPlan = $registry->planForModel(Tag::class);

    expect(collect($articlePlan->rules)->contains(
        fn (CacheInvalidationRule $rule): bool => $rule->kind === CacheInvalidationRule::KIND_FLUSH_FRONTEND_TAG,
    ))->toBeTrue()
        ->and(collect($tagPlan->rules)->contains(
            fn (CacheInvalidationRule $rule): bool => $rule->kind === CacheInvalidationRule::KIND_FLUSH_FRONTEND_TAG,
        ))->toBeTrue();
});
