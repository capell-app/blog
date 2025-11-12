<?php

declare(strict_types=1);

namespace Capell\Blog\View\Components\Widget\Page;

use App\Models\User;
use Capell\Blog\Services\Loader\TagLoader;
use Capell\Core\Models\Page;
use Capell\Frontend\Facades\FrontendLoader;
use Capell\Frontend\Services\Loader\PageLoader;
use Capell\Layout\View\Components\Widget\AbstractWidget;

class Article extends AbstractWidget
{
    // @phpstan-ignore-next-line
    public ?User $author = null;

    public ?Page $nextPage = null;

    public ?Page $previousPage = null;

    public ?Page $tagPage = null;

    public $tags = [];

    protected static string $defaultView = 'capell-blog::components.widget.page.article';

    public function render(array $data = [])
    {
        return parent::render([
            ...$data,
            'tagPage' => $this->tagPage,
            'tags' => $this->tags,
            'previousPage' => $this->previousPage,
            'nextPage' => $this->nextPage,
            'author' => $this->author,
        ]);
    }

    protected function mountWidget(): void
    {
        $page = FrontendLoader::getPage();
        $language = FrontendLoader::getLanguage();
        $site = FrontendLoader::getSite();

        if (empty($page->type->meta['hidden']) && ! empty($this->widget->meta['with_next_prev'])) {
            $this->previousPage = PageLoader::getPreviousPage($page, $site, $language);
            $this->nextPage = PageLoader::getNextPage($page, $site, $language);
        }

        if (! empty($this->widget->meta['with_author']) && ! empty($page->meta['author_id'])) {
            $this->author = PageLoader::getPageAuthor($page);
        }

        $this->tags = TagLoader::getPageTags($page);

        if ($this->tags->isNotEmpty()) {
            $this->tagPage = TagLoader::getTagResultsPage($site, $language);
        }
    }
}
