<?php

declare(strict_types=1);

namespace Capell\Blog\Support;

use Capell\Core\Enums\LayoutEnum;
use Capell\LayoutBuilder\Contracts\LayoutSidebarWidgetContributor;
use Capell\LayoutBuilder\Data\LayoutSidebarWidgetData;

class BlogSidebarWidgetContributor implements LayoutSidebarWidgetContributor
{
    public function sidebarWidgets(): array
    {
        return [
            new LayoutSidebarWidgetData(
                widgetKey: 'latest-articles',
                layoutKeys: [
                    LayoutEnum::Default->value,
                    LayoutEnum::Results->value,
                ],
                meta: ['hide_no_results' => true],
            ),
            new LayoutSidebarWidgetData(
                widgetKey: 'tags',
                layoutKeys: [
                    LayoutEnum::Default->value,
                    LayoutEnum::Results->value,
                ],
                meta: ['hide_no_results' => true],
            ),
            new LayoutSidebarWidgetData(
                widgetKey: 'archives',
                layoutKeys: [
                    LayoutEnum::Results->value,
                ],
                meta: ['hide_no_results' => true],
            ),
        ];
    }
}
