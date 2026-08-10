<?php

declare(strict_types=1);

namespace Capell\Blog\Support\PublicUrls;

use Capell\Blog\Providers\BlogServiceProvider;
use Capell\Blog\Support\Sitemap\ArchivesSitemap;
use Capell\Blog\Support\Sitemap\ArticlesSitemap;
use Capell\Blog\Support\Sitemap\TagsSitemap;
use Capell\Core\Models\Language;
use Capell\Core\Models\Site;
use Capell\Core\Models\SiteDomain;
use Capell\DiscoveryFoundation\Contracts\PublicUrlContributor;
use Capell\DiscoveryFoundation\Data\PublicUrlData;
use Capell\DiscoveryFoundation\Enums\PublicUrlContentType;
use Capell\SiteDiscovery\Data\SitemapPageData;
use Illuminate\Support\Collection;

final class BlogPublicUrlContributor implements PublicUrlContributor
{
    /**
     * @return Collection<int, PublicUrlData>
     */
    public function publicUrls(): Collection
    {
        return SiteDomain::query()
            ->with(['site', 'language'])
            ->get()
            ->filter(fn (SiteDomain $domain): bool => $domain->site instanceof Site && $domain->language instanceof Language)
            ->flatMap(fn (SiteDomain $domain): Collection => $this->publicUrlsForDomain($domain))
            ->unique(fn (PublicUrlData $url): string => $url->site->getKey() . '|' . $url->language->getKey() . '|' . $url->canonicalUrl)
            ->values();
    }

    /**
     * @return Collection<int, PublicUrlData>
     */
    private function publicUrlsForDomain(SiteDomain $domain): Collection
    {
        $site = $domain->site;
        $language = $domain->language;

        if (! $site instanceof Site || ! $language instanceof Language) {
            return collect();
        }

        return collect([
            new ArticlesSitemap($site, $domain, $language),
            new ArchivesSitemap($site, $domain, $language),
            new TagsSitemap($site, $domain, $language),
        ])
            ->flatMap(fn (ArticlesSitemap|ArchivesSitemap|TagsSitemap $sitemap): Collection => $sitemap->fetch())
            ->flatMap(fn (SitemapPageData $page): Collection => $this->flattenPage($page))
            ->filter(fn (SitemapPageData $page): bool => $page->url !== '')
            ->map(fn (SitemapPageData $page): PublicUrlData => new PublicUrlData(
                canonicalUrl: $page->url,
                sourcePackage: BlogServiceProvider::$packageName,
                site: $site,
                language: $language,
                lastModified: $page->lastModified,
                contentType: $this->contentTypeFor($page),
                isSitemapEligible: true,
                isAiDiscoveryEligible: true,
                priority: $page->priority !== null ? number_format($page->priority, 1, '.', '') : null,
                changeFrequency: $page->changeFrequency,
                title: $page->label,
            ));
    }

    /**
     * @return Collection<int, SitemapPageData>
     */
    private function flattenPage(SitemapPageData $page): Collection
    {
        return collect([$page])
            ->merge($page->children instanceof Collection
                ? $page->children->flatMap(fn (SitemapPageData $child): Collection => $this->flattenPage($child))
                : collect());
    }

    private function contentTypeFor(SitemapPageData $page): PublicUrlContentType
    {
        $pageableType = is_string($page->pageableType) ? strtolower($page->pageableType) : '';

        if (str_contains($pageableType, 'article')) {
            return PublicUrlContentType::Article;
        }

        if (str_contains($pageableType, 'tag')) {
            return PublicUrlContentType::Taxonomy;
        }

        return PublicUrlContentType::Page;
    }
}
