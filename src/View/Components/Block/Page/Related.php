<?php

declare(strict_types=1);

namespace Capell\Blog\View\Components\Block\Page;

use Capell\Blog\Support\Loader\TagLoader;
use Capell\Core\Models\Page;
use Capell\FoundationTheme\View\Components\Block\Page\AbstractPagesBlock;
use Capell\Frontend\Facades\Frontend;
use Capell\Frontend\Support\Loader\PageLoader;
use Illuminate\Contracts\Database\Eloquent\Builder as BuilderContract;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Relations\Relation;

class Related extends AbstractPagesBlock
{
    protected static string $defaultView = 'capell-layout-builder::components.block.asset.pages';

    protected function mountBlock(): void
    {
        $limit = $this->block->meta['limit'] ?? config('capell-frontend.pagination_limit', 12);

        $page = Frontend::page();

        $tags = TagLoader::getPageTags($page);

        $tagIds = $tags->pluck('id')->toArray();

        $excludeParent = $page->hasPageHierarchy() && (bool) ($this->block->meta['exclude_parent'] ?? false);

        $morphModel = $this->block->getMeta('page_model');

        $modelClass = null;

        if ($morphModel !== null) {
            $modelClass = Relation::getMorphedModel($morphModel);
        }

        $this->pages = PageLoader::getPages(
            language: Frontend::language(),
            site: Frontend::site(),
            limit: $limit,
            withChildrenCount: $page->type->meta['with_children_count'] ?? true,
            withImage: $this->block->meta['with_image'] ?? false,
            withParent: $this->block->meta['with_parent'] ?? false,
            withDate: $this->block->meta['with_date'] ?? false,
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
                            $this->block->meta['exclude_types'] ?? false,
                            fn (BuilderContract $query): BuilderContract => $query->whereNotIn(
                                'blueprints.key',
                                $this->block->meta['exclude_types'] ?? [],
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
