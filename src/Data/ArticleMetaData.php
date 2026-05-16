<?php

declare(strict_types=1);

namespace Capell\Blog\Data;

use Capell\Core\Models\Page;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use Spatie\LaravelData\Data;

final class ArticleMetaData extends Data
{
    /**
     * @param  Collection<int, mixed>  $tags
     */
    public function __construct(
        public readonly Collection $tags,
        public readonly ?Page $tagPage = null,
        public readonly ?Model $author = null,
        public readonly bool $withAuthor = false,
    ) {}

    public function shouldRender(): bool
    {
        return $this->tags->isNotEmpty() || ($this->withAuthor && $this->author instanceof Model);
    }
}
