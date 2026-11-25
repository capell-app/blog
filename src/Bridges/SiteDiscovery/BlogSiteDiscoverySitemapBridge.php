<?php

declare(strict_types=1);

namespace Capell\Blog\Bridges\SiteDiscovery;

use Capell\Core\Facades\CapellCore;
use Capell\SiteDiscovery\Support\Sitemap\SitemapPageRegistry;
use Illuminate\Contracts\Foundation\Application;

/**
 * The only place Blog names the Site Discovery sitemap registry. Nothing is registered unless Site
 * Discovery is available.
 */
final class BlogSiteDiscoverySitemapBridge
{
    public static function registerSitemapPages(Application $app): void
    {
        if (! CapellCore::isPackageAvailable('capell-app/site-discovery')) {
            return;
        }

        $registry = $app->make(SitemapPageRegistry::class);
        $registry->register('archives', ArchivesSitemap::class);
        $registry->register('articles', ArticlesSitemap::class);
        $registry->register('tags', TagsSitemap::class);
    }
}
