<?php

declare(strict_types=1);

use Capell\Blog\Data\BlogTagLinkData;
use Capell\Blog\Support\Creator\BlogCreator;
use Capell\Core\Models\Language;
use Capell\Core\Models\Site;
use Capell\Tags\Models\Tag;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

test('tag links are built from hydrated models without database queries', function (): void {
    $language = Language::factory()->english()->create();
    $site = Site::factory()->recycle($language)->withTranslations()->create();
    $tagPage = resolve(BlogCreator::class)->createTagPage($site);
    $tag = Tag::factory()->create([
        'name' => ['en' => 'Product news'],
        'slug' => ['en' => 'product-news'],
    ]);

    $tagPage->load('pageUrl.siteDomain');
    Cache::flush();
    DB::flushQueryLog();
    DB::enableQueryLog();

    $links = BlogTagLinkData::collectionFromTags(collect([$tag]), $tagPage, $language);

    expect($links)->toHaveCount(1)
        ->and($links[0]->name)->toBe('Product news')
        ->and($links[0]->url)->toEndWith('/product-news')
        ->and(DB::getQueryLog())->toBeEmpty();
});
