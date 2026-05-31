<?php

declare(strict_types=1);

namespace Capell\Blog\View\Components\Footer;

use Capell\Blog\Data\BlogTagLinkData;
use Capell\Blog\Support\Loader\TagLoader;
use Capell\Core\Contracts\Pageable;
use Capell\Core\Models\Language;
use Capell\Core\Models\Page;
use Capell\Core\Models\Site;
use Capell\Frontend\Facades\Frontend;
use Illuminate\Contracts\View\View as ViewContract;
use Illuminate\Support\Collection;
use Illuminate\View\Component;

class Tags extends Component
{
    public ?Page $tagPage = null;

    public ?Language $language = null;

    /** @var list<BlogTagLinkData> */
    public array $tagLinks = [];

    /**
     * @var Collection<array-key, mixed>
     */
    public Collection $tags;

    /**
     * @param  array<array-key, mixed>  $item
     */
    public function __construct(public array $item)
    {
        $language = Frontend::language();
        $site = Frontend::site();

        if (! $language instanceof Language || ! $site instanceof Site) {
            $this->tags = collect();

            return;
        }

        $this->tags = TagLoader::getTags($site, $language, limit: 5, hasArticles: true);

        if ($this->tags->isEmpty()) {
            return;
        }

        $tagPage = TagLoader::getTagResultsPage($site, $language);
        if (! $tagPage instanceof Pageable) {
            return;
        }

        $this->tagPage = $tagPage;
        $this->language = $language;
        $this->tagLinks = BlogTagLinkData::collectionFromTags($this->tags, $tagPage, $language);
    }

    public function render(): ViewContract|string
    {
        if (! $this->tagPage instanceof Pageable || $this->tags->isEmpty()) {
            return '';
        }

        return view('capell-blog::components.footer.tags', [
            ...$this->item,
            'tagPage' => $this->tagPage,
            'tagLinks' => $this->tagLinks,
            'tags' => $this->tags,
        ]);
    }
}
