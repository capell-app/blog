<?php

declare(strict_types=1);

namespace Capell\Blog\View\Components;

use Illuminate\View\Component;

class AssetAfterTitle extends Component
{
    public function __construct(public $publishDate = null, public $publishDatePosition = null, public $tags = null, public $publishDateOutput = null) {}

    public function render()
    {
        if (
            (! $this->publishDate || $this->publishDatePosition !== 'bottom')
            && (empty($this->tags) || $this->tags->isEmpty())
        ) {
            return '';
        }

        return view('capell-blog::hooks.asset-after-title', [
            'publishDate' => $this->publishDate,
            'publishDatePosition' => $this->publishDatePosition,
            'tags' => $this->tags,
            'publishDateOutput' => $this->publishDateOutput,
        ]);
    }
}
