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
                'elements' => [
                    ['element_key' => 'siblings'],
                    ['element_key' => 'latest-pages'],
                ],
            ],
        ],
        'elements' => ['siblings', 'latest-pages'],
    ]);

    Layout::query()->create([
        'name' => 'Results',
        'key' => LayoutEnum::Results->value,
        'containers' => [
            'sidebar' => [
                'elements' => [
                    ['element_key' => 'latest-pages'],
                ],
            ],
        ],
        'elements' => ['latest-pages'],
    ]);

    EnsureArticlePublishingDefaultsAction::run();

    $sidebarElementKeys = collect($layout->refresh()->containers['sidebar']['elements'])
        ->pluck('element_key')
        ->all();

    expect($sidebarElementKeys)
        ->toContain('latest-pages')
        ->toContain('latest-articles');
});
