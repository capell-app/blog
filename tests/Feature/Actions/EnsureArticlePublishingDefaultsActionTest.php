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
                'blocks' => [
                    ['block_key' => 'siblings'],
                    ['block_key' => 'latest-pages'],
                ],
            ],
        ],
        'blocks' => ['siblings', 'latest-pages'],
    ]);

    Layout::query()->create([
        'name' => 'Results',
        'key' => LayoutEnum::Results->value,
        'containers' => [
            'sidebar' => [
                'blocks' => [
                    ['block_key' => 'latest-pages'],
                ],
            ],
        ],
        'blocks' => ['latest-pages'],
    ]);

    EnsureArticlePublishingDefaultsAction::run();

    $sidebarBlockKeys = collect($layout->refresh()->containers['sidebar']['blocks'])
        ->pluck('block_key')
        ->all();

    expect($sidebarBlockKeys)
        ->toContain('latest-pages')
        ->toContain('latest-articles');
});
