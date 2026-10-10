<?php

declare(strict_types=1);

use Capell\Blog\Data\ArchiveMonthData;
use Capell\Blog\Models\Article;
use Capell\Blog\Support\Creator\BlogCreator;
use Capell\Blog\Support\StaticSite\BlogStaticSiteExtension;
use Capell\Core\Models\Language;
use Capell\Core\Models\Site;
use Capell\Core\Models\SiteDomain;
use Capell\HtmlCache\Support\StaticSite\StaticSiteExtensionRegistry;
use Capell\HtmlCache\Support\StaticSite\StaticSiteGenerator;
use Capell\HtmlCache\Support\StaticSite\StaticSiteRequestObserver;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\Http\Kernel as HttpKernel;
use Illuminate\Database\Eloquent\Factories\Sequence;
use Illuminate\Http\Client\Events\ResponseReceived;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Http;
use Symfony\Component\HttpFoundation\Response;

it('generates archive URLs for static site', function (): void {
    $blogCreator = resolve(BlogCreator::class);

    $archiveDate = CarbonImmutable::now()->subMonths(2);

    $language = Language::factory()->create();
    $site = Site::factory()->recycle($language)->withTranslations(siteDomainData: [
        'scheme' => 'https',
        'domain' => '8.8.8.8',
        'path' => null,
        'status' => true,
    ])->create();
    $domain = $site->siteDomains->sole();

    $articles = Article::factory()
        ->count(2)
        ->site($site)
        ->withTranslations()
        ->state([
            'visible_from' => $archiveDate,
        ])
        ->sequence(fn (Sequence $sequence): array => ['name' => 'Static archive article ' . $sequence->index])
        ->create();

    $blogPage = $blogCreator->createBlogPage($site);

    $archivesPage = $blogCreator->createArchivesPage($blogPage);
    $archivePage = $blogCreator->createArchivePage($archivesPage);
    $archiveUrl = rtrim(blogTestPageUrl($archivePage->pageUrl)->url, '/*') . '/';

    // Fake HTTP responses for all expected URLs
    $httpFakes = [];

    $archiveMonth = ArchiveMonthData::fromDate($archiveDate);
    $httpFakes[$archiveUrl . $archiveMonth->year . '/' . str_pad((string) $archiveMonth->month, 2, '0', STR_PAD_LEFT)] = Http::response('ok', 200);
    Http::fake($httpFakes);

    $visited = [];
    $extension = new BlogStaticSiteExtension;
    $extension($site, $domain, function (string $url) use (&$visited): void {
        $visited[] = $url;
    });

    $expectedUrls = [
        $archiveUrl . $archiveMonth->year . '/' . str_pad((string) $archiveMonth->month, 2, '0', STR_PAD_LEFT),
    ];

    expect($visited)->not()->toBeEmpty();

    foreach ($expectedUrls as $expectedUrl) {
        expect($visited)->toContain($expectedUrl);
    }
});

it('resolves relative blog extension URLs for both static generation request modes', function (bool $internal): void {
    $archiveDate = CarbonImmutable::now()->subMonths(2);
    $language = Language::factory()->create();
    $site = Site::factory()->recycle($language)->withTranslations(siteDomainData: [
        'scheme' => 'https',
        'domain' => '8.8.8.8',
        'path' => '/en',
        'default' => true,
        'status' => true,
    ])->create();
    Article::factory()
        ->site($site)
        ->withTranslations()
        ->state(['visible_from' => $archiveDate])
        ->create();

    $blogCreator = resolve(BlogCreator::class);
    $blogPage = $blogCreator->createBlogPage($site);
    $archivesPage = $blogCreator->createArchivesPage($blogPage);
    $archivePage = $blogCreator->createArchivePage($archivesPage);
    $archiveMonth = ArchiveMonthData::fromDate($archiveDate);
    $relativeUrl = rtrim(blogTestPageUrl($archivePage->pageUrl)->url, '/*')
        . '/' . $archiveMonth->year
        . '/' . str_pad((string) $archiveMonth->month, 2, '0', STR_PAD_LEFT);
    $expectedUrl = 'https://8.8.8.8/en' . $relativeUrl;
    $visited = [];
    $registry = new StaticSiteExtensionRegistry;
    $registry->register('blog-tags-archives', new BlogStaticSiteExtension);

    app()->instance(StaticSiteExtensionRegistry::class, $registry);
    $observer = new StaticSiteRequestObserver;
    app()->instance(StaticSiteRequestObserver::class, $observer);
    Event::listen(ResponseReceived::class, [$observer, 'record']);
    config(['capell-html-cache.static_generation.internal_requests' => $internal]);

    if ($internal) {
        $kernel = Mockery::mock(HttpKernel::class);
        $kernel->shouldReceive('handle')
            ->zeroOrMoreTimes()
            ->andReturnUsing(function (Request $request) use (&$visited): Response {
                $visited[] = $request->getSchemeAndHttpHost() . $request->getRequestUri();

                return new Response('generated', Response::HTTP_OK);
            });
        $kernel->shouldReceive('terminate')->zeroOrMoreTimes();
        app()->instance(HttpKernel::class, $kernel);
    } else {
        Http::fake(['*' => Http::response('generated', Response::HTTP_OK)]);
    }

    try {
        new StaticSiteGenerator($site)->process();
    } finally {
        $registry->clear();
    }

    if ($internal) {
        expect($visited)->toContain($expectedUrl);
    } else {
        Http::assertSent(fn (Illuminate\Http\Client\Request $request): bool => $request->url() === $expectedUrl);
    }
})->with([
    'external requests' => false,
    'internal requests' => true,
]);

it('skips archive URLs when the archive page has no URL row', function (): void {
    $archiveDate = CarbonImmutable::now()->subMonths(2);
    $blogCreator = resolve(BlogCreator::class);

    $language = Language::factory()->create();
    $site = Site::factory()->recycle($language)->withTranslations()->create();
    $domain = SiteDomain::factory()->language($language)->for($site)->create();

    Article::factory()
        ->count(2)
        ->site($site)
        ->withTranslations()
        ->state(['visible_from' => $archiveDate])
        ->sequence(fn (Sequence $sequence): array => ['name' => 'Multilingual static article ' . $sequence->index])
        ->create();

    $blogPage = $blogCreator->createBlogPage($site);
    $archivesPage = $blogCreator->createArchivesPage($blogPage);
    $archivePage = $blogCreator->createArchivePage($archivesPage);
    $archivePage->pageUrls()->delete();
    $archivePage->unsetRelation('pageUrl');

    $visited = [];
    (new BlogStaticSiteExtension)($site, $domain, function (string $url) use (&$visited): void {
        $visited[] = $url;
    });

    expect($archivePage->pageUrl)->toBeNull()
        ->and($visited)->toBeEmpty();
});
