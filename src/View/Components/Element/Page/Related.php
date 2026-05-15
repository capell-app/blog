<?php

declare(strict_types=1);

namespace Capell\Blog\View\Components\Element\Page;

use Capell\Blog\Support\Loader\TagLoader;
use Capell\Core\Models\Page;
use Capell\FoundationTheme\View\Components\Element\Page\AbstractPagesElement;
use Capell\Frontend\Facades\Frontend;
use Capell\Frontend\Support\Loader\PageLoader;
use Illuminate\Contracts\Database\Eloquent\Builder as BuilderContract;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Relations\Relation;

class Related extends AbstractPagesElement
{
    protected static string $defaultView = 'capell-layout-builder::components.element.asset.pages';

    protected function mountElement(): void
    {
        $limit = $this->element->meta['limit'] ?? config('capell-frontend.pagination_limit', 12);

        $page = Frontend::page();

        $tags = TagLoader::getPageTags($page);

        $tagIds = $tags->pluck('id')->toArray();

        $excludeParent = $page->hasPageHierarchy() && (bool) ($this->element->meta['exclude_parent'] ?? false);

        $morphModel = $this->element->getMeta('page_model');

        $modelClass = null;

        if ($morphModel !== null) {
            $modelClass = Relation::getMorphedModel($morphModel);
        }

        $this->pages = PageLoader::getPages(
            language: Frontend::language(),
            site: Frontend::site(),
            limit: $limit,
            withChildrenCount: $page->type->meta['with_children_count'] ?? true,
            withImage: $this->element->meta['with_image'] ?? false,
            withParent: $this->element->meta['with_parent'] ?? false,
            withDate: $this->element->meta['with_date'] ?? false,
            cacheKeyPrepend: 'tags-' . implode('-', $tagIds),
            morphModel: $modelClass,
            /**
             * @param  Builder<Page>  $query
             */
            modifyQuery: fn (Builder $query) => $query
                ->where('pages.id', '!=', $page->id)
                ->when(
                    $excludeParent && $page->parent_id !== null,
                    fn (BuilderContract $query): BuilderContract => $query->where('pages.id', '!=', $page->parent_id),
                )
                ->whereHas(
                    'type',
                    fn (Builder $query): Builder => $query->enabled()
                        ->listable()
                        ->accessible()
                        ->when(
                            $this->element->meta['exclude_types'] ?? false,
                            fn (BuilderContract $query): BuilderContract => $query->whereNotIn(
                                'blueprints.key',
                                $this->element->meta['exclude_types'] ?? [],
                            ),
                        ),
                )
                ->when(
                    $tags instanceof Collection && $tags->isNotEmpty(),
                    fn (Builder $query): Builder => $query->whereHas(
                        'tags',
                        fn (BuilderContract $query): BuilderContract => $query->whereIn('taggables.tag_id', $tagIds),
                    ),
                ),
        );

        $this->skipRender = $this->pages->isEmpty();
    }
}
