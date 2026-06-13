<?php

declare(strict_types=1);

namespace Capell\Blog\View\Components\Widget\Page;

use Capell\Blog\Actions\BuildArticleMetaDataAction;
use Capell\Blog\Data\ArticleMetaData;
use Capell\Blog\Data\ArticleNeighborLinkData;
use Capell\Blog\Data\ArticleWidgetRenderData;
use Capell\Blog\Models\Article as ArticleModel;
use Capell\Core\Contracts\Pageable;
use Capell\Core\Models\Language;
use Capell\Core\Models\Site;
use Capell\FoundationTheme\View\Components\Widget\AbstractWidget;
use Capell\Frontend\Facades\Frontend;
use Capell\Frontend\Support\Loader\PageLoader;
use Closure;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Model;
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

        $page = Frontend::page();
        $language = Frontend::language();
        $site = Frontend::site();

        if (! $page instanceof Pageable || ! $language instanceof Language || ! $site instanceof Site) {
            $this->skipRender = true;

            return;
        }

        $preparedArticleMeta = Frontend::getFrontendData('blog.article.meta');
        $preparedArticleRenderData = Frontend::getFrontendData('blog.article.render_data');

        if ($preparedArticleMeta instanceof ArticleMetaData && $preparedArticleRenderData instanceof ArticleWidgetRenderData) {
            $this->articleMeta = $preparedArticleMeta;
            $this->articleRenderData = $preparedArticleRenderData;

            if ($this->articleMeta->author instanceof Authenticatable) {
                $this->author = $this->articleMeta->author;
            }

            return;
        }

        if ($page instanceof ArticleModel) {
            $page->loadMissing('image');
        }

        $pageTranslation = $page instanceof Model
            ? ArticleWidgetRenderData::loadedRelation($page, 'translation')
            : null;
        $pageType = $page instanceof Model
            ? ArticleWidgetRenderData::loadedRelation($page, 'type')
            : null;
        $siteDomain = $site->relationLoaded('siteDomain') ? $site->getRelation('siteDomain') : null;
        $articleImage = $page instanceof Model
            ? ArticleWidgetRenderData::loadedRelation($page, 'image')
            : null;
        $pageTypeMeta = $pageType instanceof Model && is_array($pageType->getAttribute('meta'))
            ? $pageType->getAttribute('meta')
            : [];

        if (! isset($pageTypeMeta['hidden']) && (bool) $this->widget->getMeta('with_next_prev')) {
            $this->previousPage = PageLoader::getPreviousPage($page, $site, $language);
            $this->nextPage = PageLoader::getNextPage($page, $site, $language);
        }

        $this->articleMeta = BuildArticleMetaDataAction::run(
            page: $page,
            site: $site,
            language: $language,
            withAuthor: (bool) $this->widget->getMeta('with_author'),
        );

        if (! $this->articleMeta instanceof ArticleMetaData) {
            $this->skipRender = true;

            return;
        }

        if ($this->articleMeta->author instanceof Authenticatable) {
            $this->author = $this->articleMeta->author;
        }

        $authorProfileImage = $this->author instanceof Model
            ? ArticleWidgetRenderData::loadedRelation($this->author, 'profileImage')
            : null;

        $this->articleRenderData = new ArticleWidgetRenderData(
            title: is_string($pageTranslation?->getAttribute('title')) ? $pageTranslation->getAttribute('title') : null,
            label: is_string($pageTranslation?->getAttribute('label')) ? $pageTranslation->getAttribute('label') : null,
            summary: is_string($pageTranslation?->getAttribute('summary')) ? $pageTranslation->getAttribute('summary') : null,
            content: is_string($pageTranslation?->getAttribute('content')) ? $pageTranslation->getAttribute('content') : null,
            contentStructure: $pageType?->getAttribute('content_structure'),
            image: $articleImage,
            authorProfileImage: $authorProfileImage,
            publishedDate: $page instanceof Model ? ($page->getAttribute('visible_from') ?: $page->getAttribute('created_at')) : null,
            blogUrl: $page->getParentUrl($language, true),
            homeUrl: is_string($siteDomain?->getAttribute('url')) ? $siteDomain->getAttribute('url') : null,
            previous: ArticleNeighborLinkData::fromPage($this->previousPage),
            next: ArticleNeighborLinkData::fromPage($this->nextPage),
        );
    }
}
