<?php

declare(strict_types=1);

namespace Capell\Blog\Support\StaticSite;

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

        if ($tagPage->pageUrl === null) {
            return;
        }

        $tagsQuery = TagLoader::getTagsQuery($site, $language);
        $tagsQuery->chunk(100, function (Collection $tags) use ($tagPage, $language, $visit): void {
            $tags->each(function (Tag $tag) use ($tagPage, $language, $visit): void {
                $base = rtrim($tagPage->pageUrl->url, '/*');
                $slug = $tag->getTranslation('slug', $language->code);
                $url = $base . '/' . $slug;
                $visit($url);
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

        $archives = BlogLoader::getArchives(
            $site,
            $language,
            BlogTypeGroupEnum::Article->value,
        );

        $archives->each(function (ArchiveMonthData $archive) use ($pageModel, $site, $language, $visit): void {
            $archivePage = $pageModel::getFirstPageByTypeForSite('archive', $site, $language);
            if ($archivePage === null || $archivePage->pageUrl === null) {
                return;
            }

            $base = rtrim($archivePage->pageUrl->url, '/*');
            $url = $base . '/' . $archive->year . '/' . str_pad((string) $archive->month, 2, '0', STR_PAD_LEFT);
            $visit($url);
        });
    }
}
