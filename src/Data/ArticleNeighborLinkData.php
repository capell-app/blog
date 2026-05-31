<?php

declare(strict_types=1);

namespace Capell\Blog\Data;

use Capell\Core\Contracts\Pageable;
use Illuminate\Database\Eloquent\Model;
use Spatie\LaravelData\Data;

final class ArticleNeighborLinkData extends Data
{
    public function __construct(
        public readonly string $url,
        public readonly string $title,
        public readonly string $label,
        public readonly ?string $summary = null,
    ) {}

    public static function fromPage(?Pageable $page): ?self
    {
        if (! $page instanceof Model) {
            return null;
        }

        $pageUrl = ArticleBlockRenderData::loadedRelation($page, 'pageUrl');
        $translation = ArticleBlockRenderData::loadedRelation($page, 'translation');

        if (! $pageUrl instanceof Model || ! $translation instanceof Model) {
            return null;
        }

        $urlPath = $pageUrl->getAttribute('url');
        if (! is_string($urlPath) || $urlPath === '') {
            return null;
        }

        $url = $pageUrl->getAttribute('full_url');
        $title = $translation->getAttribute('title');
        $label = $translation->getAttribute('label');

        if (! is_string($url) || $url === '' || ! is_string($title) || $title === '' || ! is_string($label) || $label === '') {
            return null;
        }

        $summary = $translation->getAttribute('summary');

        return new self(
            url: $url,
            title: $title,
            label: $label,
            summary: is_string($summary) && $summary !== '' ? $summary : null,
        );
    }
}
