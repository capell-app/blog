<?php

declare(strict_types=1);

namespace Capell\Blog\Actions;

use Capell\Blog\Data\BlogResultItemData;
use Capell\Blog\Data\BlogResultsViewData;
use Capell\Core\Enums\AssetComponentEnum;
use Capell\Frontend\Facades\Frontend;
use Capell\Frontend\Support\View\PublicModelMeta;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use Lorisleiva\Actions\Concerns\AsFake;
use Lorisleiva\Actions\Concerns\AsObject;

final class BuildBlogResultsViewDataAction
{
    use AsFake;
    use AsObject;

    /**
     * @param  Collection<array-key, mixed>|LengthAwarePaginator<array-key, mixed>|null  $results
     */
    public function handle(Collection|LengthAwarePaginator|null $results): BlogResultsViewData
    {
        $page = Frontend::page();
        $translation = $page instanceof Model && $page->relationLoaded('translation')
            ? $page->getRelation('translation')
            : null;

        $columns = (int) PublicModelMeta::get($page, 'columns', 1);

        return new BlogResultsViewData(
            component: (string) data_get($page, 'meta.component', AssetComponentEnum::Page->value),
            componentItem: (string) data_get($page, 'meta.component_item', AssetComponentEnum::Card->value),
            noResultsText: $translation instanceof Model ? PublicModelMeta::get($translation, 'no_results') : null,
            columns: min(3, max(1, $columns)),
            withImage: (bool) PublicModelMeta::get($page, 'with_image', false),
            withPaginationSummary: ! Frontend::getFrontendData('has_pagination_summary'),
            resultItems: $this->prepareResultItems($results),
        );
    }

    /**
     * @param  Collection<array-key, mixed>|LengthAwarePaginator<array-key, mixed>|null  $results
     * @return list<BlogResultItemData>
     */
    private function prepareResultItems(Collection|LengthAwarePaginator|null $results): array
    {
        if ($results === null) {
            return [];
        }

        $items = $results instanceof LengthAwarePaginator
            ? collect($results->items())
            : $results->values();

        return array_values($items
            ->map(fn (mixed $item): BlogResultItemData => $item instanceof Model
                ? BlogResultItemData::fromModel($item)
                : BlogResultItemData::blank())
            ->values()
            ->all());
    }
}
