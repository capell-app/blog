<?php

declare(strict_types=1);

use Capell\Blog\Actions\EnsureArticlePublishingDefaultsAction;
use Capell\Blog\Enums\BlogLayoutEnum;
use Capell\Blog\Enums\BlogPageTypeEnum;
use Capell\Core\Enums\LayoutEnum;
use Capell\Core\Models\Blueprint;
use Capell\Core\Models\Layout;
use Capell\LayoutBuilder\Actions\InstallPackageAction as LayoutBuilderInstallPackageAction;
use Capell\LayoutBuilder\Enums\ElementComponentEnum;
use Capell\LayoutBuilder\Models\Element;

beforeEach(function (): void {
    LayoutBuilderInstallPackageAction::run();
});

it('installs article publishing page types layouts and elements', function (): void {
    EnsureArticlePublishingDefaultsAction::run();

    expect(Blueprint::query()->pageType()->where('key', BlogPageTypeEnum::Article->value)->exists())->toBeTrue()
        ->and(Blueprint::query()->pageType()->where('key', BlogPageTypeEnum::Blog->value)->exists())->toBeTrue()
        ->and(Blueprint::query()->pageType()->where('key', BlogPageTypeEnum::Archive->value)->exists())->toBeTrue()
        ->and(Blueprint::query()->pageType()->where('key', BlogPageTypeEnum::Tag->value)->exists())->toBeTrue()
        ->and(Layout::query()->where('key', BlogLayoutEnum::Article->value)->exists())->toBeTrue()
        ->and(Layout::query()->where('key', BlogLayoutEnum::BlogPage->value)->exists())->toBeTrue()
        ->and(Layout::query()->where('key', BlogLayoutEnum::Archives->value)->exists())->toBeTrue()
        ->and(Layout::query()->where('key', BlogLayoutEnum::TagResults->value)->exists())->toBeTrue()
        ->and(Layout::query()->where('key', BlogLayoutEnum::Tags->value)->exists())->toBeTrue()
        ->and(Element::query()->where('key', 'article')->exists())->toBeTrue()
        ->and(Element::query()->where('key', 'latest-articles')->exists())->toBeTrue()
        ->and(Element::query()->where('key', 'archives')->exists())->toBeTrue()
        ->and(Element::query()->where('key', 'tags')->exists())->toBeTrue()
        ->and(Element::query()->where('key', 'related-pages')->exists())->toBeTrue();

    $articleType = Blueprint::query()->pageType()->where('key', BlogPageTypeEnum::Article->value)->firstOrFail();
    $articleLayout = Layout::query()->where('key', BlogLayoutEnum::Article->value)->firstOrFail();
    $latestArticlesElement = Element::query()->where('key', 'latest-articles')->firstOrFail();

    expect($articleType->getMeta('with_next_prev'))->toBeTrue()
        ->and($articleType->getMeta('suppress_layout_neighbor_links'))->toBeTrue()
        ->and($latestArticlesElement->component)->toBe(ElementComponentEnum::PageLatest->value)
        ->and($latestArticlesElement->is_livewire)->toBeFalse()
        ->and($articleLayout->containers)->toHaveKey('latest')
        ->and(array_column($articleLayout->containers['sidebar']['elements'], 'element_key'))->not->toContain('latest-articles')
        ->and(array_column($articleLayout->containers['latest']['elements'], 'element_key'))->toContain('latest-articles');
});

it('updates default and results sidebars with article publishing elements', function (): void {
    EnsureArticlePublishingDefaultsAction::run();

    $defaultLayout = Layout::query()->firstWhere('key', LayoutEnum::Default->value);
    $resultsLayout = Layout::query()->firstWhere('key', LayoutEnum::Results->value);

    $defaultContainers = $defaultLayout->getAttribute('containers');
    $resultsContainers = $resultsLayout->getAttribute('containers');

    expect($defaultContainers)->toBeArray()
        ->and($resultsContainers)->toBeArray();

    $defaultSidebarElementKeys = array_column($defaultContainers['sidebar']['elements'], 'element_key');
    $resultsSidebarElementKeys = array_column($resultsContainers['sidebar']['elements'], 'element_key');

    expect($defaultSidebarElementKeys)->toContain('latest-articles')
        ->and($defaultSidebarElementKeys)->not->toContain('latest-pages')
        ->and($resultsSidebarElementKeys)->toContain('latest-articles')
        ->and($resultsSidebarElementKeys)->toContain('archives')
        ->and($resultsSidebarElementKeys)->not->toContain('latest-pages');
});
