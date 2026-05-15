<?php

declare(strict_types=1);

namespace Capell\Blog\Support;

use Capell\Core\Enums\LayoutEnum;
use Capell\LayoutBuilder\Contracts\LayoutSidebarElementContributor;
use Capell\LayoutBuilder\Data\LayoutSidebarElementData;

class BlogSidebarElementContributor implements LayoutSidebarElementContributor
{
    public function sidebarElements(): array
    {
        return [
            new LayoutSidebarElementData(
                elementKey: 'latest-articles',
                layoutKeys: [
                    LayoutEnum::Default->value,
                    LayoutEnum::Results->value,
                ],
                meta: ['hide_no_results' => true],
            ),
            new LayoutSidebarElementData(
                elementKey: 'tags',
                layoutKeys: [
                    LayoutEnum::Default->value,
                    LayoutEnum::Results->value,
                ],
                meta: ['hide_no_results' => true],
            ),
            new LayoutSidebarElementData(
                elementKey: 'archives',
                layoutKeys: [
                    LayoutEnum::Results->value,
                ],
                meta: ['hide_no_results' => true],
            ),
        ];
    }
}
