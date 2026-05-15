<?php

declare(strict_types=1);

namespace Capell\Blog\View\Components\Element\Tag;

use Capell\Blog\Support\Loader\TagLoader;
use Capell\Core\Models\Page;
use Capell\FoundationTheme\View\Components\Element\AbstractElement;
use Capell\Frontend\Facades\Frontend;
use Closure;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Collection;

class Tags extends AbstractElement
{
    public ?Page $tagPage = null;

    public ?Collection $tags = null;

    protected static string $defaultView = 'capell-blog::components.element.tag.tags';

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

        $site = Frontend::site();
        $language = Frontend::language();

        $this->tags = TagLoader::getTags(
            site: $site,
            language: $language,
            limit: $limit,
            hasArticles: true,
        );

        $this->tagPage = TagLoader::getTagResultsPage($site, $language);

        if (! $this->tagPage instanceof Page) {
            $this->skipRender = true;

            return;
        }

        if ($this->tags->isNotEmpty()) {
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
