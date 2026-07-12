<?php

declare(strict_types=1);

namespace Capell\Blog\Data;

use Capell\Core\Models\Language;
use Capell\Core\Models\Page;
use Capell\Tags\Models\Tag;
use Illuminate\Support\Collection;
use Spatie\LaravelData\Data;

final class BlogTagLinkData extends Data
{
    public function __construct(
        public readonly string $url,
        public readonly string $name,
        public readonly ?int $count = null,
    ) {}

    /**
     * @param  Collection<array-key, mixed>  $tags
     * @return list<self>
     */
    public static function collectionFromTags(Collection $tags, Page $tagPage, Language $language): array
    {
        return array_values($tags
            ->filter(fn (mixed $tag): bool => $tag instanceof Tag)
            ->map(function (Tag $tag) use ($tagPage, $language): self {
                $attributes = $tag->getAttributes();
                $count = $attributes['taggables_count'] ?? null;

                return new self(
                    url: $tag->getUrl($tagPage, $language),
                    name: $tag->getTranslation('name', $language->code),
                    count: is_numeric($count) ? (int) $count : null,
                );
            })
            ->values()
            ->all());
    }
}
