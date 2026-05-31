<?php

declare(strict_types=1);

namespace Capell\Blog\View\Components\Block\Tag;

use Capell\Blog\Actions\BuildTagListingDataAction;
use Capell\Blog\Data\BlogBlockContentData;
use Capell\Blog\Data\BlogTagLinkData;
use Capell\Blog\Data\TagListingData;
use Capell\Core\Models\Language;
use Capell\Core\Models\Page;
use Capell\Core\Models\Site;
use Capell\Core\Models\Theme;
use Capell\FoundationTheme\View\Components\Block\AbstractBlock;
use Capell\Frontend\Facades\Frontend;
use Capell\Tags\Models\Tag;
use Closure;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Override;

class Tags extends AbstractBlock
{
    public ?Page $tagPage = null;

    /**
     * @var LengthAwarePaginator<int, Tag>|Collection<int, Tag>|null
     */
    public Collection|LengthAwarePaginator|null $tags = null;

    public ?TagListingData $tagListing = null;

    public BlogBlockContentData $contentData;

    /** @var list<BlogTagLinkData> */
    public array $tagLinks = [];

    public string $noResultsText;

    public bool $withDarkMode = false;

    protected static string $defaultView = 'capell-blog::components.block.tag.tags';

    #[Override]
    public function render(array $data = []): View|string|Closure
    {
        return parent::render([
            ...$data,
            'tagPage' => $this->tagPage,
            'tags' => $this->tags,
            'tagLinks' => $this->tagLinks,
            'contentData' => $this->contentData,
            'noResultsText' => $this->noResultsText,
            'withDarkMode' => $this->withDarkMode,
        ]);
    }

    protected function mountBlock(): void
    {
        $this->contentData = new BlogBlockContentData;
        $this->noResultsText = __('capell-blog::messages.no_tags_found');

        $limit = $this->block->meta['limit'] ?? null;
        $limit = is_numeric($limit) ? (int) $limit : null;

        $withPagination = (bool) $this->block->getMeta('pagination');
        $occurrence = is_numeric($this->blockData['occurrence'] ?? null) ? (int) $this->blockData['occurrence'] : $this->blockIndex + 1;
        $paginationKey = sprintf('tags-%s-%s-%d', $this->containerKey, $this->block->getKey(), $occurrence);
        $requestedPage = request()->query($paginationKey, 1);
        $paginationPage = $withPagination && is_numeric($requestedPage) ? (int) $requestedPage : null;

        $site = Frontend::site();
        $language = Frontend::language();
        $page = Frontend::page();
        $theme = Frontend::theme();

        if (! $site instanceof Site || ! $language instanceof Language) {
            $this->skipRender = true;

            return;
        }

        $tagListing = BuildTagListingDataAction::run(
            site: $site,
            language: $language,
            limit: $limit,
            paginationPage: $paginationPage,
            withPagination: $withPagination,
            paginationKey: $paginationKey,
        );

        if (! $tagListing instanceof TagListingData) {
            $this->skipRender = true;

            return;
        }

        $this->tagListing = $tagListing;
        $this->tags = $this->tagListing->tags;
        $this->tagPage = $this->tagListing->tagPage;
        $this->withDarkMode = $theme instanceof Theme && (bool) $theme->withDarkMode;
        $this->contentData = $this->buildContentData($page, $theme);
        $this->noResultsText = $this->translatedNoResultsText();

        if (! $this->tagPage instanceof Page) {
            $this->skipRender = true;

            return;
        }

        $this->tagLinks = BlogTagLinkData::collectionFromTags(collect($this->tags), $this->tagPage, $language);

        if (count($this->tags) > 0) {
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
        $pageType = $page instanceof Model && $page->relationLoaded('type') ? $page->getRelation('type') : null;
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
            contentType: $this->stringAttribute($blockTranslation, 'content') !== null
                ? $blockType?->getAttribute('content_structure')
                : ($showPageContent ? $pageType?->getAttribute('content_structure') : null),
            divider: $this->block->getMeta('content_divider'),
            textAlign: $this->block->getMeta('align'),
            headingStyle: $this->block->getMeta('heading_style'),
            muted: in_array($this->containerKey, $secondaryContainers, true),
            headingTag: $showPageTitle ? 'h1' : null,
        );
    }

    private function translatedNoResultsText(): string
    {
        $translation = $this->block->relationLoaded('translation') ? $this->block->getRelation('translation') : null;
        $meta = $translation instanceof Model && is_array($translation->getAttribute('meta')) ? $translation->getAttribute('meta') : [];
        $text = $meta['no_results'] ?? null;

        return is_string($text) && $text !== '' ? $text : __('capell-blog::messages.no_tags_found');
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
