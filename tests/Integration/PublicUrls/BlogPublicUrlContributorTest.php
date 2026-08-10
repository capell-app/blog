<?php

declare(strict_types=1);

use Capell\Blog\Models\Article;
use Capell\Blog\Providers\BlogServiceProvider;
use Capell\Blog\Support\Creator\BlogCreator;
use Capell\Blog\Support\PublicUrls\BlogPublicUrlContributor;
use Capell\Core\Models\SiteDomain;
use Capell\DiscoveryFoundation\Contracts\PublicUrlContributor;
use Capell\DiscoveryFoundation\Data\PublicUrlData;
use Capell\DiscoveryFoundation\Enums\PublicUrlContentType;

it('contributes blog article and listing URLs to the public URL registry contract', function (): void {
    $blogCreator = resolve(BlogCreator::class);

    $siteDomain = SiteDomain::factory()->default()->create();
    $site = $siteDomain->site;

    $blogPage = $blogCreator->createBlogPage($site);
    $articleType = $blogCreator->createArticlePageType();
    $articleLayout = $blogCreator->createArticleLayout();

    $article = Article::factory()
        ->site($site)
        ->layout($articleLayout)
        ->type($articleType)
        ->withTranslations($site->languages)
        ->create();
    $blogPageUrl = $blogPage->pageUrl;
    $articlePageUrl = $article->pageUrl;

    throw_if($blogPageUrl === null || $articlePageUrl === null, RuntimeException::class, 'Expected blog fixtures to create page URLs.');

    $urls = (new BlogPublicUrlContributor)->publicUrls();

    expect($urls->first())->toBeInstanceOf(PublicUrlData::class)
        ->and($urls->pluck('canonicalUrl')->all())->toContain(
            $blogPageUrl->full_url,
            $articlePageUrl->full_url,
        )
        ->and($urls->first(fn (PublicUrlData $url): bool => $url->canonicalUrl === $articlePageUrl->full_url)?->sourcePackage)
        ->toBe(BlogServiceProvider::$packageName)
        ->and($urls->first(fn (PublicUrlData $url): bool => $url->canonicalUrl === $articlePageUrl->full_url)?->contentType)
        ->toBe(PublicUrlContentType::Article)
        ->and($urls->first(fn (PublicUrlData $url): bool => $url->canonicalUrl === $articlePageUrl->full_url)?->title)
        ->toBe($article->translation->label ?? $article->name);
});

it('registers the blog public URL contributor when Site Discovery is available', function (): void {
    $contributors = collect(app()->tagged(PublicUrlContributor::TAG));

    expect($contributors->contains(fn (mixed $contributor): bool => $contributor instanceof BlogPublicUrlContributor))->toBeTrue();
});
