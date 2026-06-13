<?php

declare(strict_types=1);

namespace Capell\Blog\View\Components\Widget\Page;

use Capell\Blog\Support\Loader\TagLoader;
use Capell\Core\Contracts\Pageable;
use Capell\Core\Models\Language;
use Capell\Core\Models\Page;
use Capell\Core\Models\Site;
use Capell\FoundationTheme\View\Components\Widget\Page\AbstractPagesWidget;
use Capell\Frontend\Facades\Frontend;
use Capell\Frontend\Support\Loader\PageLoader;
use Illuminate\Contracts\Database\Eloquent\Builder as BuilderContract;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Support\Collection;

class Related extends AbstractPagesWidget
{
    protected static string $defaultView = 'capell-layout-builder::components.widget.asset.pages';

    protected function mountWidget(): void
    {
        $limit = $this->widget->meta['limit'] ?? config('capell-frontend.pagination_limit', 12);
        $fallbackLimit = config('capell-frontend.pagination_limit', 12);
        $limit = is_numeric($limit)
            ? (int) $limit
            : (is_int($fallbackLimit) ? $fallbackLimit : 12);

        $page = Frontend::page();
        $language = Frontend::language();
        $site = Frontend::site();

        if (! $page instanceof Pageable || ! $language instanceof Language || ! $site instanceof Site) {
            $this->skipRender = true;

            return;
        }

        $preparedRelatedArticles = Frontend::getFrontendData('blog.related_articles');

        if ($preparedRelatedArticles instanceof Collection) {
            $this->pages = $preparedRelatedArticles->take($limit);
            $this->skipRender = $this->pages->isEmpty();

            return;
        }

        $tags = TagLoader::getPageTags($page);

        $tagIds = $tags->pluck('id')->toArray();

        $excludeParent = $page->hasPageHierarchy() && (bool) ($this->widget->meta['exclude_parent'] ?? false);

        $morphModel = $this->widget->getMeta('page_model');

        $modelClass = null;

        if ($morphModel !== null) {
            $resolvedModelClass = Relation::getMorphedModel($morphModel);

            if (is_string($resolvedModelClass) && is_subclass_of($resolvedModelClass, Pageable::class)) {
                /** @var class-string<Pageable<Model>> $resolvedModelClass */
                $modelClass = $resolvedModelClass;
            }
        }

        $this->pages = PageLoader::getPages(
            language: $language,
            site: $site,
            limit: $limit,
            withChildrenCount: $page->hasPageHierarchy() && ($page->type->meta['with_children_count'] ?? true),
            withImage: $this->widget->meta['with_image'] ?? false,
            withParent: $this->widget->meta['with_parent'] ?? false,
            withDate: $this->widget->meta['with_date'] ?? false,
            cacheKeyPrepend: 'tags-' . implode('-', $tagIds),
            morphModel: $modelClass,
            /**
             * @param  Builder<Page>  $query
             */
            modifyQuery: function (Builder $query) use ($excludeParent, $page, $tagIds, $tags): void {
                $idColumn = $query->getModel()->qualifyColumn('id');

                $query->where($idColumn, '!=', $page->id)
                    ->when(
                        $excludeParent && $page->parent_id !== null,
                        fn (BuilderContract $query): BuilderContract => $query->where($idColumn, '!=', $page->parent_id),
                    )
                    ->whereHas(
                        'type',
                        fn (Builder $query): Builder => $query->enabled()
                            ->listable()
                            ->accessible()
                            ->when(
                                $this->widget->meta['exclude_types'] ?? false,
                                fn (BuilderContract $query): BuilderContract => $query->whereNotIn(
                                    'blueprints.key',
                                    $this->widget->meta['exclude_types'] ?? [],
                                ),
                            ),
                    )
                    ->when(
                        $tags->isNotEmpty(),
                        fn (Builder $query): Builder => $query->whereHas(
                            'tags',
                            fn (BuilderContract $query): BuilderContract => $query->whereIn('taggables.tag_id', $tagIds),
                        ),
                    );
            },
        );

        $this->skipRender = $this->pages->isEmpty();
    }
}
