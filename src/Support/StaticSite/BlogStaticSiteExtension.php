<?php

declare(strict_types=1);

namespace Capell\Blog\Support\StaticSite;

use Capell\Blog\Actions\GenerateArchiveUrlAction;
use Capell\Blog\Data\ArchiveMonthData;
use Capell\Blog\Enums\BlogTypeGroupEnum;
use Capell\Blog\Support\Loader\BlogLoader;
use Capell\Blog\Support\Loader\TagLoader;
use Capell\Core\Models\Language;
use Capell\Core\Models\Page;
use Capell\Core\Models\Site;
use Capell\Core\Models\SiteDomain;
use Capell\Tags\Models\Tag;
use Illuminate\Support\Collection;

class BlogStaticSiteExtension
{
    public function __invoke(Site $site, SiteDomain $domain, callable $visit): void
    {
        /** @var class-string<Page> $pageModel */
        $pageModel = Page::class;

        $this->visitTaggedPages($pageModel, $site, $domain, $visit);
        $this->visitArchivePages($pageModel, $site, $domain, $visit);
    }

    /**
     * @param  class-string<Page>  $pageModel
     */
    private function visitTaggedPages(string $pageModel, Site $site, SiteDomain $domain, callable $visit): void
    {
        $language = $domain->language;
        if (! $language instanceof Language) {
            return;
        }

        $tagPage = $pageModel::getFirstPageByTypeForSite('tag', $site, $language);
        if ($tagPage === null) {
            return;
        }

        $tagPageUrl = $tagPage->pageUrl;
        if ($tagPageUrl === null || ! is_string($tagPageUrl->url) || $tagPageUrl->url === '') {
            return;
        }

        $tagPageUrl->setRelation('siteDomain', $domain);
        $tagsQuery = TagLoader::getTagsQuery($site, $language);
        $tagsQuery->chunk(100, function (Collection $tags) use ($tagPage, $language, $visit): void {
            $tags->each(function (Tag $tag) use ($tagPage, $language, $visit): void {
                // The tag model builds the same URL the public route and sitemap use.
                $visit($tag->getUrl($tagPage, $language));
            });
        });
    }

    /**
     * @param  class-string<Page>  $pageModel
     */
    private function visitArchivePages(string $pageModel, Site $site, SiteDomain $domain, callable $visit): void
    {
        $language = $domain->language;
        if (! $language instanceof Language) {
            return;
        }

        $archivePage = $pageModel::getFirstPageByTypeForSite('archive', $site, $language);
        $archivePageUrl = $archivePage?->pageUrl;
        if ($archivePageUrl === null || ! is_string($archivePageUrl->url) || $archivePageUrl->url === '') {
            return;
        }

        $archivePageUrl->setRelation('siteDomain', $domain);

        $archives = BlogLoader::getArchives(
            $site,
            $language,
            BlogTypeGroupEnum::Article->value,
        );

        // The sitemap uses this action too, so generated URLs follow the archive route's YYYY-MM format.
        $archives->each(function (ArchiveMonthData $archive) use ($archivePageUrl, $visit): void {
            $visit(GenerateArchiveUrlAction::run($archivePageUrl, $archive));
        });
    }
}
