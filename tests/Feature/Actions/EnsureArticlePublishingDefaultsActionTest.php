<?php

declare(strict_types=1);

use Capell\Blog\Actions\EnsureArticlePublishingDefaultsAction;
use Capell\Core\Enums\LayoutEnum;
use Capell\Core\Models\Layout;

it('keeps latest pages in default sidebars when adding latest articles', function (): void {
    $layout = Layout::query()->create([
        'name' => 'Default',
        'key' => LayoutEnum::Default->value,
        'containers' => [
            'sidebar' => [
                'widgets' => [
                    ['widget_key' => 'siblings'],
                    ['widget_key' => 'latest-pages'],
                ],
            ],
        ],
        'widgets' => ['siblings', 'latest-pages'],
    ]);

    Layout::query()->create([
        'name' => 'Results',
        'key' => LayoutEnum::Results->value,
        'containers' => [
            'sidebar' => [
                'widgets' => [
                    ['widget_key' => 'latest-pages'],
                ],
            ],
        ],
        'widgets' => ['latest-pages'],
    ]);

    EnsureArticlePublishingDefaultsAction::run();

    $sidebarWidgetKeys = collect($layout->refresh()->containers['sidebar']['widgets'])
        ->pluck('widget_key')
        ->all();

    expect($sidebarWidgetKeys)
        ->toContain('latest-pages')
        ->toContain('latest-articles');
});
