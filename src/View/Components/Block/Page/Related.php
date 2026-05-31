<?php

declare(strict_types=1);

namespace Capell\Blog\View\Components\Block\Page;

use Capell\Blog\Support\Loader\TagLoader;
use Capell\Core\Contracts\Pageable;
use Capell\Core\Models\Language;
use Capell\Core\Models\Page;
use Capell\Core\Models\Site;
use Capell\FoundationTheme\View\Components\Block\Page\AbstractPagesBlock;
use Capell\Frontend\Facades\Frontend;
use Capell\Frontend\Support\Loader\PageLoader;
use Illuminate\Contracts\Database\Eloquent\Builder as BuilderContract;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\Relation;

class Related extends AbstractPagesBlock
{
    protected static string $defaultView = 'capell-layout-builder::components.block.asset.pages';

    protected function mountBlock(): void
    {
        $limit = $this->block->meta['limit'] ?? config('capell-frontend.pagination_limit', 12);

        $page = Frontend::page();
        $language = Frontend::language();
        $site = Frontend::site();

        if (! $page instanceof Pageable || ! $language instanceof Language || ! $site instanceof Site) {
            $this->skipRender = true;

            return;
        }

        $tags = TagLoader::getPageTags($page);

        $tagIds = $tags->pluck('id')->toArray();

        $excludeParent = $page->hasPageHierarchy() && (bool) ($this->block->meta['exclude_parent'] ?? false);

        $morphModel = $this->block->getMeta('page_model');

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
            withImage: $this->block->meta['with_image'] ?? false,
            withParent: $this->block->meta['with_parent'] ?? false,
            withDate: $this->block->meta['with_date'] ?? false,
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
                                $this->block->meta['exclude_types'] ?? false,
                                fn (BuilderContract $query): BuilderContract => $query->whereNotIn(
                                    'blueprints.key',
                                    $this->block->meta['exclude_types'] ?? [],
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
