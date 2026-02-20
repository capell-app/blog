<?php

declare(strict_types=1);

namespace Capell\Blog\View\Components\Footer;

use Capell\Blog\Enums\BlogTypeGroupEnum;
use Capell\Frontend\Facades\Frontend;
use Capell\Frontend\Support\Loader\PageLoader;
use Illuminate\Support\Collection;
use Illuminate\View\Component;

class Pages extends Component
{
    public Collection $pages;

    public function __construct(public array $item)
    {
        $this->pages = PageLoader::getPages(
            language: Frontend::language(),
            site: Frontend::site(),
            limit: 3,
            ordering: 'latest',
            pageGroup: BlogTypeGroupEnum::Article,
            withImage: true,
        );
    }

    public function render()
    {
        return view('capell-blog::components.footer.pages', [
            ...$this->item,
            'pages' => $this->pages,
        ]);
    }
}
