<?php

declare(strict_types=1);

namespace Capell\Blog\Actions;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Lorisleiva\Actions\Concerns\AsFake;
use Lorisleiva\Actions\Concerns\AsObject;

/**
 * Restricts a related-articles listing to articles that share a tag with the current
 * article and orders them by how many tags they share. An article without tags has
 * nothing to relate by, so it matches no articles rather than every listable page.
 *
 * @method static Builder<*> run(Builder<*> $query, mixed $excludedKey, array<array-key, mixed> $tagIds)
 */
final class ApplyRelatedArticleRankingAction
{
    use AsFake;
    use AsObject;

    /**
     * @template TModel of Model
     *
     * @param  Builder<TModel>  $query
     * @param  array<array-key, mixed>  $tagIds
     * @return Builder<TModel>
     */
    public function handle(Builder $query, mixed $excludedKey, array $tagIds): Builder
    {
        $query->where($query->getModel()->qualifyColumn('id'), '!=', $excludedKey);

        if ($tagIds === []) {
            return $query->whereRaw('0 = 1');
        }

        return $query
            ->whereHas('tags', fn (Builder $tags): Builder => $tags->whereIn('taggables.tag_id', $tagIds))
            ->withCount(['tags as shared_tags_count' => fn (Builder $tags): Builder => $tags->whereIn('taggables.tag_id', $tagIds)])
            // The listing loader orders newest-first before it calls us, so replace that order: shared tags
            // lead, and the newest article (highest id) breaks ties.
            ->reorder()
            ->orderByDesc('shared_tags_count')
            ->orderByDesc($query->getModel()->qualifyColumn('id'));
    }
}
