<?php

declare(strict_types=1);

namespace Capell\Blog\View\Components;

use Capell\Blog\Support\Loader\TagLoader;
use Capell\Core\Models\Page;
use Capell\Frontend\Facades\Frontend;
use Exception;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use Illuminate\View\Component;
use Illuminate\View\View;

class ArticleMeta extends Component
{
    public ?Page $tagPage = null;

    public Collection $tags;

    public function __construct(public bool $withAuthor = false, public ?Model $author = null)
    {
        if ($this->withAuthor && ! $this->author instanceof Model) {
            $page = Frontend::page();

            if ($page instanceof Model) {
                $page->loadMissing('creator');

                $creator = $page->getRelation('creator');

                if ($creator instanceof Model) {
                    $this->author = $creator;
                }
            }

        }

        $this->tags = TagLoader::getPageTags(Frontend::page());

        if ($this->tags->isNotEmpty()) {
            $site = Frontend::site();
            $language = Frontend::language();

            $this->tagPage = TagLoader::getTagResultsPage($site, $language);

            throw_unless(
                $this->tagPage,
                Exception::class,
                'Tag results page not found for the current site ' . $site->id . ' and language ' . $language->id,
            );
        }
    }

    public function render(): string|View
    {
        if ($this->tags->isEmpty() && ($this->withAuthor && ! $this->author instanceof Model)) {
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
