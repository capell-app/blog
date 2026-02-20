<?php

declare(strict_types=1);

namespace Capell\Blog\View\Components\Page;

use Illuminate\View\Component;

class BeforeContentTags extends Component
{
    public function __construct(public $item, public $tags) {}

    public function render()
    {
        if (! $this->tags || $this->tags->isEmpty()) {
            return '';
        }

        return view('capell-blog::page.tags', [
            'item' => $this->item,
            'tags' => $this->tags,
        ]);
    }
}
