<?php

declare(strict_types=1);

namespace Capell\Blog\View\Components\Element\Tag;

use Capell\Blog\Actions\BuildTagListingDataAction;
use Capell\Blog\Data\TagListingData;
use Capell\Core\Models\Page;
use Capell\FoundationTheme\View\Components\Element\AbstractElement;
use Capell\Frontend\Facades\Frontend;
use Closure;
use Illuminate\Contracts\View\View;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Override;

class Tags extends AbstractElement
{
    public ?Page $tagPage = null;

    public Collection|LengthAwarePaginator|null $tags = null;

    public ?TagListingData $tagListing = null;

    protected static string $defaultView = 'capell-blog::components.element.tag.tags';

    #[Override]
    public function render(array $data = []): View|string|Closure
    {
        return parent::render([
            ...$data,
            'tagPage' => $this->tagPage,
            'tags' => $this->tags,
        ]);
    }

    protected function mountElement(): void
    {
        $limit = $this->element->meta['limit'] ?? null;
        $limit = is_numeric($limit) ? (int) $limit : null;

        $withPagination = (bool) $this->element->getMeta('pagination');
        $occurrence = is_numeric($this->elementData['occurrence'] ?? null) ? (int) $this->elementData['occurrence'] : $this->elementIndex + 1;
        $paginationKey = sprintf('tags-%s-%s-%d', $this->containerKey, $this->element->getKey(), $occurrence);
        $requestedPage = request()->query($paginationKey, 1);
        $paginationPage = $withPagination && is_numeric($requestedPage) ? (int) $requestedPage : null;

        $site = Frontend::site();
        $language = Frontend::language();

        $this->tagListing = BuildTagListingDataAction::run(
            site: $site,
            language: $language,
            limit: $limit,
            paginationPage: $paginationPage,
            withPagination: $withPagination,
            paginationKey: $paginationKey,
        );

        $this->tags = $this->tagListing->tags;
        $this->tagPage = $this->tagListing->tagPage;

        if (! $this->tagPage instanceof Page) {
            $this->skipRender = true;

            return;
        }

        if (count($this->tags) > 0) {
            return;
        }

        if (isset($this->elementData['meta']['hide_no_results']) && $this->elementData['meta']['hide_no_results']) {
            $this->skipRender = true;
        }

        if (config('capell-layout-builder.element.skip_render_empty') === true) {
            $this->skipRender = true;
        }
    }
}
