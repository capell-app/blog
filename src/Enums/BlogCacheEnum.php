<?php

declare(strict_types=1);

namespace Capell\Blog\Enums;

enum BlogCacheEnum: string
{
    case SitemapTagPages = 'sitemap.tag_pages.%d.%d';

    public static function sitemapTagPages(int $siteId, int $languageId): string
    {
        return sprintf(self::SitemapTagPages->value, $siteId, $languageId);
    }
}
