<?php

declare(strict_types=1);

namespace Capell\Blog\View\Components\Element\Page;

use Capell\Blog\Enums\BlogTypeGroupEnum;
use Capell\Blog\Support\Loader\BlogLoader;
use Capell\Core\Contracts\Pageable;
use Capell\Core\Models\Page;
use Capell\FoundationTheme\View\Components\Element\AbstractElement;
use Capell\Frontend\Facades\Frontend;
use Closure;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Collection;

class Archives extends AbstractElement
{
    protected ?Page $archivePage = null;

    protected null|Collection|LengthAwarePaginator $archives = null;

    protected static string $defaultView = 'capell-blog::components.element.page.archives';

    public function render(array $data = []): View|string|Closure
    {
        return parent::render([
            ...$data,
            'archivePage' => $this->archivePage,
            'archives' => $this->archives,
        ]);
    }

    protected function mountElement(): void
    {
        $language = Frontend::language();
        $site = Frontend::site();

        $this->archivePage = BlogLoader::getArchivePage($site, $language);

        if (! $this->archivePage instanceof Pageable) {
            $this->skipRender = true;

            return;
        }

        $group = $this->element->meta['page_group'] ?? BlogTypeGroupEnum::Article->value;

        $limit = $this->element->meta['limit'] ?? config('capell-frontend.pagination_limit', 12);

        $this->archives = BlogLoader::getArchives(
            site: $site,
            language: $language,
            group: $group,
            limit: $limit,
        );

        if ($this->archives->isNotEmpty()) {
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
