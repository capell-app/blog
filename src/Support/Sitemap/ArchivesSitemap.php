<?php

declare(strict_types=1);

namespace Capell\Blog\Support\Sitemap;

use Capell\Blog\Actions\GenerateArchiveUrl;
use Capell\Blog\Data\ArchiveMonthData;
use Capell\Blog\Enums\BlogTypeGroupEnum;
use Capell\Blog\Support\Loader\BlogLoader;
use Capell\Core\Contracts\Pageable;
use Capell\Core\Models\Page;
use Capell\Core\Models\PageUrl;
use Capell\SiteDiscovery\Data\SitemapPageData;
use Capell\SiteDiscovery\Support\Sitemap\AbstractSitemapPages;
use Capell\SiteDiscovery\Support\Sitemap\SitemapChainBuilder;
use Illuminate\Support\Collection;
use LogicException;

class ArchivesSitemap extends AbstractSitemapPages
{
    /**
     * @return Collection<array-key, mixed>
     */
    public function fetch(): Collection
    {
        /** @var class-string<Page> $model */
        $model = Page::class;

        $maybeArchivePage = $model::getFirstPageByTypeForSite('archive', $this->site, $this->language);
        if (! ($maybeArchivePage instanceof Pageable)) {
            return collect([]);
        }

        $archivePage = $maybeArchivePage;
        $monthChildren = $this->getArchiveMonths($archivePage);

        if ($archivePage->parent === null) {
            return $monthChildren;
        }

        $node = SitemapChainBuilder::build($archivePage->parent, $monthChildren, withEditUrl: $this->withEditUrl);

        return collect([$node]);
    }

    public function format(ArchiveMonthData $monthData, Page $archivePage): SitemapPageData
    {
        $pageUrl = $archivePage->pageUrl;

        throw_unless($pageUrl instanceof PageUrl, LogicException::class, 'Archive page requires a URL for sitemap generation.');

        return new SitemapPageData(
            label: $monthData->getDate()->format('F Y') . ' (' . $monthData->total . ')',
            url: GenerateArchiveUrl::run($pageUrl, $monthData),
        );
    }

    /**
     * @return Collection<array-key, mixed>
     */
    private function getArchiveMonths(Page $archivePage): Collection
    {
        $archives = BlogLoader::getArchives(
            $this->site,
            $this->language,
            BlogTypeGroupEnum::Article->value,
        );

        return $archives->map(
            fn (ArchiveMonthData $archive): SitemapPageData => $this->format($archive, $archivePage),
        )->values();
    }
}
