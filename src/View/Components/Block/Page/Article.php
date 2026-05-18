<?php

declare(strict_types=1);

namespace Capell\Blog\View\Components\Block\Page;

use Capell\Blog\Actions\BuildArticleMetaDataAction;
use Capell\Blog\Data\ArticleMetaData;
use Capell\Core\Contracts\Pageable;
use Capell\FoundationTheme\View\Components\Block\AbstractBlock;
use Capell\Frontend\Facades\Frontend;
use Capell\Frontend\Support\Loader\PageLoader;
use Closure;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Contracts\View\View;
use Override;

class Article extends AbstractBlock
{
    public ?Authenticatable $author = null;

    public ?Pageable $nextPage = null;

    public ?Pageable $previousPage = null;

    public ?ArticleMetaData $articleMeta = null;

    protected static string $defaultView = 'capell-blog::components.block.page.article';

    #[Override]
    public function render(array $data = []): View|string|Closure
    {
        return parent::render([
            ...$data,
            'author' => $this->author,
            'previousPage' => $this->previousPage,
            'nextPage' => $this->nextPage,
            'articleMetaData' => $this->articleMeta,
        ]);
    }

    protected function mountBlock(): void
    {
        $page = Frontend::page();
        $language = Frontend::language();
        $site = Frontend::site();

        if (! isset($page->type->meta['hidden']) && (bool) $this->block->getMeta('with_next_prev')) {
            $this->previousPage = PageLoader::getPreviousPage($page, $site, $language);
            $this->nextPage = PageLoader::getNextPage($page, $site, $language);
        }

        $this->articleMeta = BuildArticleMetaDataAction::run(
            page: $page,
            site: $site,
            language: $language,
            withAuthor: (bool) $this->block->getMeta('with_author'),
        );

        if ($this->articleMeta->author instanceof Authenticatable) {
            $this->author = $this->articleMeta->author;
        }
    }
}
