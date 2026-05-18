<?php

declare(strict_types=1);

namespace Capell\Blog\View\Components;

use Capell\Blog\Actions\BuildArticleMetaDataAction;
use Capell\Blog\Data\ArticleMetaData;
use Capell\Core\Models\Page;
use Capell\Frontend\Facades\Frontend;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use Illuminate\View\Component;
use Illuminate\View\View;

class ArticleMeta extends Component
{
    public ?Page $tagPage = null;

    public Collection $tags;

    public function __construct(
        public bool $withAuthor = false,
        public ?Model $author = null,
        ?ArticleMetaData $articleMetaData = null,
    ) {
        $data = $articleMetaData ?? BuildArticleMetaDataAction::run(
            page: Frontend::page(),
            site: Frontend::site(),
            language: Frontend::language(),
            withAuthor: $this->withAuthor,
            author: $this->author,
        );

        $this->tags = $data->tags;
        $this->tagPage = $data->tagPage;
        $this->author = $data->author;
    }

    public function render(): string|View
    {
        if ($this->tags->isEmpty() && (! $this->withAuthor || ! $this->author instanceof Model)) {
            return '';
        }

        return view('capell-blog::components.article-meta', [
            'tagPage' => $this->tagPage,
            'tags' => $this->tags,
            'author' => $this->author,
            'withAuthor' => $this->withAuthor,
        ])->render();
    }
}
