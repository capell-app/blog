<?php

declare(strict_types=1);

use Capell\Blog\Models\Article;
use Capell\Blog\Support\Creator\BlogCreator;
use Capell\Blog\Support\StaticSite\BlogStaticSiteExtension;
use Capell\Core\Models\SiteDomain;
use Capell\Tags\Enums\TagTypeEnum;
use Capell\Tags\Models\Tag;
use Capell\Tests\Support\Concerns\TestingFrontend;
use Carbon\CarbonImmutable;

use function Pest\Laravel\get;

uses(TestingFrontend::class);

it('emits archive and tag URLs that the public routes serve', function (): void {
    $blogCreator = resolve(BlogCreator::class);

    $siteDomain = SiteDomain::factory()->default()->create();
    $site = $siteDomain->site;

    $blogPage = $blogCreator->createBlogPage($site);
    $archivesPage = $blogCreator->createArchivesPage($blogPage);
    $blogCreator->createArchivePage($archivesPage);
    $tagsPage = $blogCreator->createTagsPage($site, $blogPage);
    $blogCreator->createTagPage($site, $tagsPage);
    $tag = Tag::factory()->translate($siteDomain->language)->type(TagTypeEnum::Page)->create();
    $blogCreator->createArticlePageType();
    $articleLayout = $blogCreator->createArticleLayout();

    Article::factory()
        ->count(2)
        ->site($site)
        ->layout($articleLayout)
        ->withTranslations($site->languages)
        ->hasAttached($tag)
        ->state(['visible_from' => CarbonImmutable::now()->subMonths(2)])
        ->sequence(fn ($sequence): array => ['name' => 'Static route article ' . $sequence->index])
        ->create();

    $visited = [];
    (new BlogStaticSiteExtension)($site, $siteDomain, function (string $url) use (&$visited): void {
        $visited[] = $url;
    });

    $archiveUrls = array_values(array_filter($visited, static fn (string $url): bool => str_contains($url, '/archive')));

    $month = CarbonImmutable::now()->subMonths(2)->format('Y-m');

    expect($archiveUrls)->not->toBeEmpty()
        ->and(array_values(array_filter($archiveUrls, static fn (string $url): bool => str_ends_with($url, '/' . $month))))->not->toBeEmpty()
        ->and(array_values(array_filter($visited, static fn (string $url): bool => str_ends_with($url, '/' . $tag->getTranslation('slug', $siteDomain->language->code)))))->not->toBeEmpty();

    foreach ($visited as $url) {
        get($url)->assertOk();
    }

    // The old hand-built year/month form is not a route.
    get(str_replace($month, str_replace('-', '/', $month), $archiveUrls[0]))->assertNotFound();
});
