<?php

declare(strict_types=1);

namespace Capell\Blog\View\Components\Widget\Tag;

use Capell\Blog\Services\Loader\TagLoader;
use Capell\Core\Models\Page;
use Capell\Frontend\Facades\Frontend;
use Capell\Layout\View\Components\Widget\AbstractWidget;

class Tags extends AbstractWidget
{
    public ?Page $tagPage = null;

    public $tags;

    protected static string $defaultView = 'capell-blog::components.widget.tag.tags';

    public function render(array $data = [])
    {
        return parent::render([
            ...$data,
            'tagPage' => $this->tagPage,
            'tags' => $this->tags,
        ]);
    }

    protected function mountWidget(): void
    {
        $limit = $this->widget->meta['limit'] ?? null;

        $this->tags = TagLoader::getTags(
            site: Frontend::site(),
            language: Frontend::language(),
            limit: $limit,
        );

        if ($this->tags->isEmpty() && config('capell-layout.widget.hide_empty')) {
            $this->skipRender = true;

            return;
        }

        $this->tagPage = TagLoader::getTagResultsPage(Frontend::site(), Frontend::language());
    }
}
