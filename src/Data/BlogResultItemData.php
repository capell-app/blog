<?php

declare(strict_types=1);

namespace Capell\Blog\Data;

use Capell\Frontend\Support\View\PublicModelMeta;
use Illuminate\Database\Eloquent\Model;
use Spatie\LaravelData\Data;

final class BlogResultItemData extends Data
{
    public function __construct(
        public readonly ?Model $author = null,
        public readonly mixed $image = null,
        public readonly ?string $url = null,
        public readonly ?Model $translation = null,
        public readonly bool $squareImage = false,
        public readonly string $publishDatePosition = 'top',
    ) {}

    public static function blank(): self
    {
        return new self;
    }

    public static function fromModel(Model $item): self
    {
        $translation = self::loadedRelation($item, 'translation');
        $image = self::loadedRelation($item, 'image') ?? PublicModelMeta::get($item, 'image_source');
        $pageUrl = self::loadedRelation($item, 'pageUrl');

        return new self(
            author: self::loadedRelation($item, 'creator'),
            image: $image,
            url: is_string($pageUrl?->getAttribute('url')) && $pageUrl->getAttribute('url') !== ''
                ? (string) $pageUrl->getAttribute('full_url')
                : null,
            translation: $translation,
            squareImage: (bool) PublicModelMeta::get($item, 'square_image', false),
            publishDatePosition: (string) PublicModelMeta::get($translation, 'publish_date_position', 'top'),
        );
    }

    private static function loadedRelation(Model $model, string $relation): ?Model
    {
        if (! $model->relationLoaded($relation)) {
            return null;
        }

        $relatedModel = $model->getRelation($relation);

        return $relatedModel instanceof Model ? $relatedModel : null;
    }
}
