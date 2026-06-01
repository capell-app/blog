<?php

declare(strict_types=1);

namespace Capell\Blog\View\Components\Block\Page;

use Capell\Blog\Data\ArchiveLinkData;
use Capell\Blog\Data\ArchiveMonthData;
use Capell\Blog\Data\BlogBlockContentData;
use Capell\Blog\Enums\BlogTypeGroupEnum;
use Capell\Blog\Support\Loader\BlogLoader;
use Capell\Core\Contracts\Pageable;
use Capell\Core\Models\Language;
use Capell\Core\Models\Page;
use Capell\Core\Models\PageUrl;
use Capell\Core\Models\Site;
use Capell\Core\Models\Theme;
use Capell\FoundationTheme\View\Components\Block\AbstractBlock;
use Capell\Frontend\Facades\Frontend;
use Closure;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use Override;

class Archives extends AbstractBlock
{
    public BlogBlockContentData $contentData;

    /** @var list<ArchiveLinkData> */
    public array $archiveLinks = [];

    public string $noResultsText;

    protected ?Page $archivePage = null;

    /**
     * @var Collection<int, ArchiveMonthData>|\Illuminate\Pagination\LengthAwarePaginator<int, ArchiveMonthData>|null
     */
    protected null|Collection|LengthAwarePaginator $archives = null;

    protected static string $defaultView = 'capell-blog::components.block.page.archives';

    #[Override]
    public function render(array $data = []): View|string|Closure
    {
        return parent::render([
            ...$data,
            'archivePage' => $this->archivePage,
            'archives' => $this->archives,
            'archiveLinks' => $this->archiveLinks,
            'contentData' => $this->contentData,
            'noResultsText' => $this->noResultsText,
        ]);
    }

    protected function mountBlock(): void
    {
        $this->contentData = new BlogBlockContentData;
        $this->noResultsText = __('capell-blog::messages.no_archives_found');

        $language = Frontend::language();
        $site = Frontend::site();
        $page = Frontend::page();
        $theme = Frontend::theme();

        if (! $language instanceof Language || ! $site instanceof Site) {
            $this->skipRender = true;

            return;
        }

        $this->archivePage = BlogLoader::getArchivePage($site, $language);

        if (! $this->archivePage instanceof Pageable) {
            $this->skipRender = true;

            return;
        }

        $this->contentData = $this->buildContentData($page, $theme);
        $this->noResultsText = $this->translatedNoResultsText();

        $group = $this->block->meta['page_group'] ?? BlogTypeGroupEnum::Article->value;

        $limit = $this->block->meta['limit'] ?? config('capell-frontend.pagination_limit', 12);

        $this->archives = BlogLoader::getArchives(
            site: $site,
            language: $language,
            group: $group,
            limit: $limit,
        );

        $archivePageUrl = $this->archivePage->relationLoaded('pageUrl') ? $this->archivePage->getRelation('pageUrl') : null;
        $activeArchive = $this->activeArchive();
        if ($archivePageUrl instanceof PageUrl) {
            $archiveItems = $this->archives instanceof LengthAwarePaginator
                ? collect($this->archives->items())
                : $this->archives;

            $this->archiveLinks = array_values($archiveItems
                ->map(fn (ArchiveMonthData $archive): ArchiveLinkData => ArchiveLinkData::fromArchive($archivePageUrl, $archive, $activeArchive))
                ->values()
                ->all());
        }

        if ($this->archives->isNotEmpty()) {
            return;
        }

        if (isset($this->blockData['meta']['hide_no_results']) && $this->blockData['meta']['hide_no_results']) {
            $this->skipRender = true;
        }

        if (config('capell-layout-builder.block.skip_render_empty') === true) {
            $this->skipRender = true;
        }
    }

    private function buildContentData(mixed $page, mixed $theme): BlogBlockContentData
    {
        $blockTranslation = $this->block->relationLoaded('translation') ? $this->block->getRelation('translation') : null;
        $blockType = $this->block->relationLoaded('type') ? $this->block->getRelation('type') : null;
        $pageTranslation = $page instanceof Model && $page->relationLoaded('translation') ? $page->getRelation('translation') : null;
        $showPageContent = (bool) ($this->blockData['meta']['show_page_content'] ?? false);
        $showPageTitle = (bool) ($this->blockData['meta']['show_page_title'] ?? false);
        $title = $this->stringAttribute($blockTranslation, 'title')
            ?: ($showPageTitle ? $this->stringAttribute($pageTranslation, 'title') : null);
        $content = $this->stringAttribute($blockTranslation, 'content')
            ?: ($showPageContent ? $this->stringAttribute($pageTranslation, 'content') : null);
        $showTitle = $this->block->getMeta(sprintf('container_options.%s.hide_title', $this->containerKey)) !== true && $title !== null;
        $showContent = $this->block->getMeta(sprintf('container_options.%s.hide_content', $this->containerKey)) !== true && $content !== null;
        $secondaryContainers = $theme instanceof Theme && is_array($theme->secondary_containers) ? $theme->secondary_containers : [];

        return new BlogBlockContentData(
            show: $showTitle || $showContent,
            title: $showTitle ? $title : null,
            content: $showContent ? $content : null,
            contentType: $blockType?->getAttribute('content_structure'),
            divider: $this->block->getMeta('content_divider'),
            textAlign: $this->block->getMeta('align'),
            headingStyle: $this->block->getMeta('heading_style'),
            muted: in_array($this->containerKey, $secondaryContainers, true),
            headingTag: $showPageTitle ? 'h1' : null,
        );
    }

    private function activeArchive(): mixed
    {
        return Frontend::params()['archive_date'] ?? null;
    }

    private function translatedNoResultsText(): string
    {
        $translation = $this->block->relationLoaded('translation') ? $this->block->getRelation('translation') : null;
        $meta = $translation instanceof Model && is_array($translation->getAttribute('meta')) ? $translation->getAttribute('meta') : [];
        $text = $meta['no_results'] ?? null;

        return is_string($text) && $text !== '' ? $text : __('capell-blog::messages.no_archives_found');
    }

    private function stringAttribute(mixed $model, string $attribute): ?string
    {
        if (! $model instanceof Model) {
            return null;
        }

        $value = $model->getAttribute($attribute);

        return is_string($value) && $value !== '' ? $value : null;
    }
}
