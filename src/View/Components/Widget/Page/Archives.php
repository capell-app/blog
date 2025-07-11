<?php

declare(strict_types=1);

namespace Capell\Blog\View\Components\Widget\Page;

use Capell\Blog\Services\Loader\BlogLoader;
use Capell\Core\Models\Page;
use Capell\Frontend\Facades\Frontend;
use Capell\Layout\View\Components\Widget\AbstractWidget;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;

class Archives extends AbstractWidget
{
    protected ?Page $archivePage = null;

    protected Collection|LengthAwarePaginator $archives;

    protected static string $defaultView = 'capell-layout::components.widget.page.archives';

    public function render(array $data = [])
    {
        return parent::render([
            ...$data,
            'archivePage' => $this->archivePage,
            'archives' => $this->archives,
        ]);
    }

    protected function mountWidget(): void
    {
        $type = $this->widget->meta['page_group'] ?? 'article';

        $limit = $this->widget->meta['limit'] ?? config('capell-frontend.pagination_limit', 12);

        $this->archives = BlogLoader::getArchives(
            site: Frontend::getSite(),
            language: Frontend::getLanguage(),
            type: $type,
            limit: $limit
        );

        if ($this->archives->isEmpty() && $this->containerKey !== 'main') {
            $this->skipRender = true;

            return;
        }

        $this->archivePage = BlogLoader::getArchivePage(Frontend::getSite(), Frontend::getLanguage());
    }
}
