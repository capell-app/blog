<?php

declare(strict_types=1);

namespace Capell\Blog\View\Components\Page;

use Capell\Blog\Data\BlogTagLinkData;
use Capell\Blog\Support\Loader\TagLoader;
use Capell\Core\Contracts\Pageable;
use Capell\Core\Models\Language;
use Capell\Core\Models\Page;
use Capell\Core\Models\Site;
use Capell\Frontend\Facades\Frontend;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use Illuminate\View\Component;
use Illuminate\View\View;

class BeforeContentTags extends Component
{
    public ?Page $tagPage = null;

    public ?Language $language = null;

    /** @var list<BlogTagLinkData> */
    public array $tagLinks = [];

    /**
     * @param  Collection<array-key, mixed>  $tags
     */
    public function __construct(public ?Model $item, public Collection $tags)
    {
        $language = Frontend::language();
        $site = Frontend::site();

        if (! $language instanceof Language || ! $site instanceof Site || $this->tags->isEmpty()) {
            return;
        }

        $preparedTagPage = Frontend::getFrontendData('blog.tag_page');
        $tagPage = $preparedTagPage instanceof Page
            ? $preparedTagPage
            : TagLoader::getTagResultsPage($site, $language);

        if (! $tagPage instanceof Pageable) {
            return;
        }

        $this->language = $language;
        $this->tagPage = $tagPage;
        $this->tagLinks = BlogTagLinkData::collectionFromTags($this->tags, $tagPage, $language);
    }

    public function render(): View|string
    {
        if ($this->tagLinks === []) {
            return '';
        }

        return view('capell-blog::components.page.tags', [
            'item' => $this->item,
            'tagLinks' => $this->tagLinks,
        ]);
    }
}
