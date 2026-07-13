<?php

declare(strict_types=1);

namespace Capell\Blog\View\Components\Widget\Page;

use Capell\Blog\Data\ArticleMetaData;
use Capell\Blog\Data\ArticleWidgetRenderData;
use Capell\Core\Contracts\Pageable;
use Capell\FoundationTheme\View\Components\Widget\AbstractWidget;
use Capell\Frontend\Facades\Frontend;
use Closure;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Contracts\View\View;
use Override;

class Article extends AbstractWidget
{
    public ?Authenticatable $author = null;

    public ?Pageable $nextPage = null;

    public ?Pageable $previousPage = null;

    public ?ArticleMetaData $articleMeta = null;

    public ArticleWidgetRenderData $articleRenderData;

    protected static string $defaultView = 'capell-blog::components.widget.page.article';

    #[Override]
    public function render(array $data = []): View|string|Closure
    {
        return parent::render([
            ...$data,
            'author' => $this->author,
            'previousPage' => $this->previousPage,
            'nextPage' => $this->nextPage,
            'articleMetaData' => $this->articleMeta,
            'articleRenderData' => $this->articleRenderData,
        ]);
    }

    protected function mountWidget(): void
    {
        $this->articleRenderData = ArticleWidgetRenderData::blank();

        $preparedArticleMeta = Frontend::getFrontendData('blog.article.meta');
        $preparedArticleRenderData = Frontend::getFrontendData('blog.article.render_data');

        if (! $preparedArticleMeta instanceof ArticleMetaData || ! $preparedArticleRenderData instanceof ArticleWidgetRenderData) {
            $this->skipRender = true;

            return;
        }

        $this->articleMeta = $preparedArticleMeta;
        $this->articleRenderData = $preparedArticleRenderData;

        if ($this->articleMeta->author instanceof Authenticatable) {
            $this->author = $this->articleMeta->author;
        }
    }
}
