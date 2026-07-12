<?php

declare(strict_types=1);

namespace Capell\Blog\Data;

use Capell\Core\Models\Page;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Spatie\LaravelData\Data;

final class TagListingData extends Data
{
    /**
     * @param  Collection<int, mixed>|LengthAwarePaginator<int, mixed>  $tags
     */
    public function __construct(
        public readonly Collection|LengthAwarePaginator $tags,
        public readonly ?Page $tagPage,
    ) {}
}
