<?php

declare(strict_types=1);

namespace Capell\Blog\View\Components;

use Capell\Blog\Actions\BuildArticleMetaDataAction;
use Capell\Blog\Data\ArticleMetaData;
use Capell\Blog\Data\BlogTagLinkData;
use Capell\Core\Models\Language;
use Capell\Core\Models\Page;
use Capell\Frontend\Facades\Frontend;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use Illuminate\View\Component;
use Illuminate\View\View;

class ArticleMeta extends Component
{
    public ?Page $tagPage = null;

    public ?Language $language = null;

    /** @var list<BlogTagLinkData> */
    public array $tagLinks = [];

    /**
     * @var Collection<array-key, mixed>
     */
    public Collection $tags;

    public function __construct(
        public bool $withAuthor = false,
        public ?Model $author = null,
        public ?Model $profileImage = null,
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
        $this->tagLinks = $data->tagLinks;
        $this->tagPage = $data->tagPage;
        $this->language = $data->language;
        $this->author = $data->author;

        $profileImage = $this->author instanceof Model && $this->author->relationLoaded('profileImage')
            ? $this->author->getRelation('profileImage')
            : null;
        $this->profileImage = $profileImage instanceof Model ? $profileImage : null;
    }

    public function render(): string|View
    {
        if ($this->tags->isEmpty() && (! $this->withAuthor || ! $this->author instanceof Model)) {
            return '';
        }

        return view('capell-blog::components.article-meta', [
            'tagPage' => $this->tagPage,
            'language' => $this->language,
            'tagLinks' => $this->tagLinks,
            'tags' => $this->tags,
            'author' => $this->author,
            'profileImage' => $this->profileImage,
            'withAuthor' => $this->withAuthor,
        ])->render();
    }
}
