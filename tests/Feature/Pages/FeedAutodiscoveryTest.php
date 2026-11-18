<?php

declare(strict_types=1);

use Capell\Blog\Support\Creator\BlogCreator;
use Capell\Core\Models\SiteDomain;
use Capell\Frontend\Enums\CacheEnum;
use Capell\Tests\Support\Concerns\TestingFrontend;

use function Pest\Laravel\get;

uses(TestingFrontend::class);

beforeEach(function (): void {
    config(['capell.disable_cache_save_keys' => [CacheEnum::Pages->value . '-*']]);
});

test('a page of a site with a blog advertises the RSS and Atom feeds', function (): void {
    $siteDomain = SiteDomain::factory()->default()->create();
    $blogPage = resolve(BlogCreator::class)->createBlogPage($siteDomain->site);
    $blogUrl = blogTestPageUrl($blogPage->pageUrl);
    $origin = rtrim($siteDomain->full_url, '/');

    $html = (string) get($blogUrl->full_url)->assertOk()->getContent();

    expect($html)
        ->toMatch('#<link rel="alternate" type="application/rss\+xml" title="[^"]+" href="' . preg_quote($origin . '/blog/feed.xml', '#') . '">#')
        ->toMatch('#<link rel="alternate" type="application/atom\+xml" title="[^"]+" href="' . preg_quote($origin . '/blog/feed.atom', '#') . '">#');
});
