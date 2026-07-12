<?php

declare(strict_types=1);

namespace Capell\Blog\Data;

use Illuminate\Database\Eloquent\Model;
use Spatie\LaravelData\Data;

final class ArticleWidgetRenderData extends Data
{
    public function __construct(
        public readonly ?string $title = null,
        public readonly ?string $label = null,
        public readonly ?string $summary = null,
        public readonly ?string $content = null,
        public readonly mixed $contentStructure = null,
        public readonly mixed $image = null,
        public readonly mixed $authorProfileImage = null,
        public readonly mixed $publishedDate = null,
        public readonly ?string $blogUrl = null,
        public readonly ?string $homeUrl = null,
        public readonly ?ArticleNeighborLinkData $previous = null,
        public readonly ?ArticleNeighborLinkData $next = null,
    ) {}

    public static function blank(): self
    {
        return new self;
    }

    public static function loadedRelation(Model $model, string $relation): ?Model
    {
        if (! $model->relationLoaded($relation)) {
            return null;
        }

        $relatedModel = $model->getRelation($relation);

        return $relatedModel instanceof Model ? $relatedModel : null;
    }
}
