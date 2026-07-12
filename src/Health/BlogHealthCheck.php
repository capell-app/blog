<?php

declare(strict_types=1);

namespace Capell\Blog\Health;

use Capell\Blog\Actions\BuildArticleMetaDataAction;
use Capell\Blog\Actions\ClearBlogContentCacheAction;
use Capell\Blog\Actions\ClearBlogTagCacheAction;
use Capell\Blog\Data\ArticleMetaData;
use Capell\Blog\Enums\BlogPageTypeEnum;
use Capell\Blog\Enums\WidgetComponentEnum;
use Capell\Blog\Models\Article;
use Capell\Blog\Support\Sitemap\ArchivesSitemap;
use Capell\Blog\Support\Sitemap\ArticlesSitemap;
use Capell\Blog\Support\Sitemap\TagsSitemap;
use Capell\Blog\Support\StaticSite\BlogStaticSiteExtension;
use Capell\Core\Contracts\Extensions\ChecksExtensionHealth;
use Capell\Core\Data\Diagnostics\DoctorCheckResultData;
use Capell\Core\Enums\BlueprintSubjectEnum;
use Capell\Core\Models\Blueprint;
use Capell\SiteDiscovery\Contracts\PublicUrlContributor;
use Capell\Tags\Models\Tag;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Schema;

final class BlogHealthCheck implements ChecksExtensionHealth
{
    private const string PUBLIC_URL_CONTRIBUTOR = PublicUrlContributor::class;

    public static function compatibleCapellApiVersion(): string
    {
        return '^4.0';
    }

    /**
     * @return Collection<int, DoctorCheckResultData>
     */
    public static function runDiagnostics(): Collection
    {
        $check = new self;

        return collect([
            $check->publishingSurfaceCheck(),
            $check->cacheInvalidationCheck(),
            $check->authorRelatedRenderingCheck(),
            $check->sitemapStaticExportCheck(),
        ]);
    }

    public static function passed(): bool
    {
        return self::runDiagnostics()
            ->every(fn (DoctorCheckResultData $result): bool => $result->passed);
    }

    private function publishingSurfaceCheck(): DoctorCheckResultData
    {
        $missing = [];

        if (! Schema::hasTable('articles')) {
            $missing[] = 'articles table';
        }

        $missingPageTypes = $this->missingPageTypes();

        foreach ($missingPageTypes as $missingPageType) {
            $missing[] = sprintf('%s page type', $missingPageType);
        }

        return new DoctorCheckResultData(
            label: 'Blog publishing surface',
            passed: $missing === [],
            message: $missing === []
                ? 'Articles table and blog, archive, tag, and article page types are available.'
                : 'Missing: ' . implode(', ', $missing) . '.',
            remediation: $missing === [] ? null : 'Run capell:blog-setup after installing Blog migrations.',
        );
    }

    private function cacheInvalidationCheck(): DoctorCheckResultData
    {
        $manifestSources = $this->manifestInvalidationSources();
        $hasArticleInvalidation = collect($manifestSources)
            ->contains(fn (array $source): bool => ($source['model'] ?? null) === Article::class);
        $hasTagInvalidation = collect($manifestSources)
            ->contains(fn (array $source): bool => ($source['model'] ?? null) === Tag::class);

        $passed = class_exists(ClearBlogContentCacheAction::class)
            && class_exists(ClearBlogTagCacheAction::class)
            && $hasArticleInvalidation
            && $hasTagInvalidation;

        return new DoctorCheckResultData(
            label: 'Blog cache invalidation',
            passed: $passed,
            message: $passed
                ? 'Article and tag cache invalidation actions and manifest dependencies are declared.'
                : 'Article or tag cache invalidation actions, cache keys, or manifest dependencies are missing.',
            remediation: $passed ? null : 'Check Blog cache actions and capell.json performance.cacheSafety.invalidationSources.',
        );
    }

    private function authorRelatedRenderingCheck(): DoctorCheckResultData
    {
        $passed = class_exists(BuildArticleMetaDataAction::class)
            && class_exists(ArticleMetaData::class)
            && WidgetComponentEnum::PageRelated->value !== ''
            && WidgetComponentEnum::Article->value !== '';

        return new DoctorCheckResultData(
            label: 'Blog author and related rendering',
            passed: $passed,
            message: $passed
                ? 'Article metadata and related/article widget render definitions are available.'
                : 'Article metadata or related/article widget render definitions are missing.',
            remediation: $passed ? null : 'Check BuildArticleMetaDataAction, ArticleMetaData, and WidgetComponentEnum.',
        );
    }

    private function sitemapStaticExportCheck(): DoctorCheckResultData
    {
        $siteDiscoveryAvailable = interface_exists(self::PUBLIC_URL_CONTRIBUTOR);
        $passed = class_exists(BlogStaticSiteExtension::class);

        if ($siteDiscoveryAvailable) {
            $passed = $passed
                && class_exists(ArticlesSitemap::class)
                && class_exists(ArchivesSitemap::class)
                && class_exists(TagsSitemap::class);
        }

        return new DoctorCheckResultData(
            label: 'Blog sitemap and static export',
            passed: $passed,
            message: match (true) {
                $passed && $siteDiscoveryAvailable => 'Static export and optional Site Discovery sitemap bridge classes are available.',
                $passed => 'Static export is available; Site Discovery is not installed, so sitemap bridge checks are skipped.',
                default => 'Static export or Site Discovery sitemap bridge classes are missing.',
            },
            remediation: $passed ? null : 'Check BlogStaticSiteExtension and optional Site Discovery sitemap classes.',
        );
    }

    /**
     * @return list<string>
     */
    private function missingPageTypes(): array
    {
        if (! Schema::hasTable((new Blueprint)->getTable())) {
            return array_map(
                fn (BlogPageTypeEnum $pageType): string => $pageType->value,
                BlogPageTypeEnum::cases(),
            );
        }

        $installedPageTypes = Blueprint::query()
            ->where('type', BlueprintSubjectEnum::Page->value)
            ->whereIn('key', array_map(
                fn (BlogPageTypeEnum $pageType): string => $pageType->value,
                BlogPageTypeEnum::cases(),
            ))
            ->pluck('key')
            ->all();

        return array_values(collect(BlogPageTypeEnum::cases())
            ->map(fn (BlogPageTypeEnum $pageType): string => $pageType->value)
            ->diff($installedPageTypes)
            ->values()
            ->all());
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function manifestInvalidationSources(): array
    {
        $manifestPath = dirname(__DIR__, 2) . '/capell.json';
        $contents = file_exists($manifestPath) ? file_get_contents($manifestPath) : false;

        if ($contents === false) {
            return [];
        }

        $manifest = json_decode($contents, associative: true);
        $sources = $manifest['performance']['cacheSafety']['invalidationSources'] ?? [];

        return is_array($sources) ? array_values(array_filter($sources, is_array(...))) : [];
    }
}
