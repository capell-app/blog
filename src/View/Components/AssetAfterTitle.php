<?php

declare(strict_types=1);

namespace Capell\Blog\View\Components;

use Capell\Blog\Data\BlogTagLinkData;
use Capell\Blog\Support\Loader\TagLoader;
use Capell\Core\Contracts\Pageable;
use Capell\Core\Models\Language;
use Capell\Core\Models\Page;
use Capell\Core\Models\Site;
use Capell\Frontend\Facades\Frontend;
use Closure;
use DateTimeImmutable;
use Illuminate\Support\Collection;
use Illuminate\View\Component;
use Illuminate\View\View;

class AssetAfterTitle extends Component
{
    public ?Page $tagPage = null;

    public ?Language $language = null;

    /** @var list<BlogTagLinkData> */
    public array $tagLinks = [];

    /**
     * @param  Collection<array-key, mixed>  $tags
     */
    public function __construct(
        public ?DateTimeImmutable $publishDate = null,
        public ?string $publishDatePosition = null,
        public ?Collection $tags = null,
        public ?Closure $publishDateOutput = null,
    ) {
        $language = Frontend::language();
        $site = Frontend::site();

        if (! $language instanceof Language || ! $site instanceof Site || ! $this->tags?->isNotEmpty()) {
            return;
        }

        $tagPage = TagLoader::getTagResultsPage($site, $language);

        if (! $tagPage instanceof Pageable) {
            return;
        }

        $this->language = $language;
        $this->tagPage = $tagPage;
        $this->tagLinks = BlogTagLinkData::collectionFromTags($this->tags, $tagPage, $language);
    }

    public function render(): View|string
    {
        if (
            (! $this->publishDate instanceof DateTimeImmutable || $this->publishDatePosition !== 'bottom')
            && ! $this->tags?->isNotEmpty()
        ) {
            return '';
        }

        return view('capell-blog::components.asset-after-title', [
            'publishDate' => $this->publishDate,
            'publishDatePosition' => $this->publishDatePosition,
            'tagLinks' => $this->tagLinks,
            'tags' => $this->tags,
            'publishDateOutput' => $this->publishDateOutput,
        ]);
    }
}
