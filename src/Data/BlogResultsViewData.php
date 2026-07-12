<?php

declare(strict_types=1);

namespace Capell\Blog\Data;

use Spatie\LaravelData\Data;
use Stringable;

final class BlogResultsViewData extends Data implements Stringable
{
    /**
     * @param  list<BlogResultItemData>  $resultItems
     */
    public function __construct(
        public readonly string $component,
        public readonly string $componentItem,
        public readonly ?string $noResultsText,
        public readonly int $columns,
        public readonly bool $withImage,
        public readonly bool $withPaginationSummary,
        public readonly array $resultItems,
    ) {}

    public function __toString(): string
    {
        return '';
    }
}
