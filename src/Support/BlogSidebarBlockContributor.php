<?php

declare(strict_types=1);

namespace Capell\Blog\Support;

use Capell\Core\Enums\LayoutEnum;
use Capell\LayoutBuilder\Contracts\LayoutSidebarBlockContributor;
use Capell\LayoutBuilder\Data\LayoutSidebarBlockData;

class BlogSidebarBlockContributor implements LayoutSidebarBlockContributor
{
    public function sidebarBlocks(): array
    {
        return [
            new LayoutSidebarBlockData(
                blockKey: 'latest-articles',
                layoutKeys: [
                    LayoutEnum::Default->value,
                    LayoutEnum::Results->value,
                ],
                meta: ['hide_no_results' => true],
            ),
            new LayoutSidebarBlockData(
                blockKey: 'tags',
                layoutKeys: [
                    LayoutEnum::Default->value,
                    LayoutEnum::Results->value,
                ],
                meta: ['hide_no_results' => true],
            ),
            new LayoutSidebarBlockData(
                blockKey: 'archives',
                layoutKeys: [
                    LayoutEnum::Results->value,
                ],
                meta: ['hide_no_results' => true],
            ),
        ];
    }
}
