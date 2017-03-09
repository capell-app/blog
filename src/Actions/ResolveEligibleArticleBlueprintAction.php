<?php

declare(strict_types=1);

namespace Capell\Blog\Actions;

use Capell\Blog\Enums\BlogTypeGroupEnum;
use Capell\Core\Models\Blueprint;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Validation\ValidationException;
use Lorisleiva\Actions\Concerns\AsObject;

final class ResolveEligibleArticleBlueprintAction
{
    use AsObject;

    /** @return Builder<Blueprint> */
    public function query(): Builder
    {
        return Blueprint::query()->pageType()->where('group', BlogTypeGroupEnum::Article)
            ->enabled()->ordered()->orderBy('id');
    }

    public function handle(mixed $id = null, ?string $key = null): Blueprint
    {
        $query = $this->query();

        if ($id !== null) {
            $blueprint = (is_int($id) || (is_string($id) && ctype_digit($id)))
                ? $query->whereKey($id)->first()
                : null;
        } elseif ($key !== null) {
            $blueprint = $query->where('key', $key)->first();
        } else {
            $choices = $query->get();
            $blueprint = $choices->count() === 1 ? $choices->first() : $choices->firstWhere('default', true);
        }

        if (! $blueprint instanceof Blueprint) {
            throw ValidationException::withMessages([
                'blueprint_id' => __('capell-blog::generic.article_blueprint_unavailable'),
            ]);
        }

        return $blueprint;
    }
}
