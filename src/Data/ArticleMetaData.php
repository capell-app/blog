<?php

declare(strict_types=1);

namespace Capell\Blog\Data;

use Capell\Core\Models\Language;
use Capell\Core\Models\Page;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use Spatie\LaravelData\Data;

final class ArticleMetaData extends Data
{
    /**
     * @param  Collection<int, mixed>  $tags
     * @param  list<BlogTagLinkData>  $tagLinks
     */
    public function __construct(
        public readonly Collection $tags,
        public readonly array $tagLinks = [],
        public readonly ?Page $tagPage = null,
        public readonly ?Language $language = null,
        public readonly ?Model $author = null,
        public readonly bool $withAuthor = false,
        public readonly ?string $authorName = null,
        public readonly ?CarbonInterface $publishedAt = null,
        public readonly ?CarbonInterface $modifiedAt = null,
        public readonly ?int $readingTimeMinutes = null,
    ) {}

    public function shouldRender(): bool
    {
        if ($this->tags->isNotEmpty()) {
            return true;
        }

        return $this->withAuthor && $this->author instanceof Model;
    }
}
