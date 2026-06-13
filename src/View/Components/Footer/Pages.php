<?php

declare(strict_types=1);

namespace Capell\Blog\View\Components\Footer;

use Capell\Blog\Enums\BlogTypeGroupEnum;
use Capell\Blog\Models\Article;
use Capell\Core\Enums\PageOrderEnum;
use Capell\Core\Models\Language;
use Capell\Core\Models\Site;
use Capell\Frontend\Facades\Frontend;
use Capell\Frontend\Support\Loader\PageLoader;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Contracts\View\View as ViewContract;
use Illuminate\Support\Collection;
use Illuminate\View\Component;

class Pages extends Component
{
    /**
     * @var Collection<array-key, mixed>
     */
    public Collection $pages;

    /**
     * @param  array<array-key, mixed>  $item
     */
    public function __construct(public array $item)
    {
        $language = Frontend::language();
        $site = Frontend::site();

        if (! $language instanceof Language || ! $site instanceof Site) {
            $this->pages = collect();

            return;
        }

        $preparedPages = Frontend::getFrontendData('blog.latest_articles');

        if ($preparedPages instanceof Collection) {
            $this->pages = $preparedPages->take(3);

            return;
        }

        if ($preparedPages instanceof LengthAwarePaginator) {
            $this->pages = collect($preparedPages->items())->take(3);

            return;
        }

        $this->pages = PageLoader::getPages(
            language: $language,
            site: $site,
            limit: 3,
            ordering: PageOrderEnum::Latest,
            pageGroup: BlogTypeGroupEnum::Article,
            withImage: true,
            morphModel: Article::class,
        );
    }

    public function render(): ViewContract
    {
        return view('capell-blog::components.footer.pages', [
            ...$this->item,
            'pages' => $this->pages,
        ]);
    }
}
