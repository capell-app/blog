<?php

declare(strict_types=1);

use Capell\Blog\Actions\EnsureArticlePublishingDefaultsAction;
use Capell\Blog\Enums\BlogLayoutEnum;
use Capell\Blog\Enums\BlogPageTypeEnum;
use Capell\Core\Enums\LayoutEnum;
use Capell\Core\Models\Blueprint;
use Capell\Core\Models\Layout;
use Capell\LayoutBuilder\Actions\InstallPackageAction as LayoutBuilderInstallPackageAction;
use Capell\LayoutBuilder\Enums\WidgetComponentEnum;
use Capell\LayoutBuilder\Models\Widget;

beforeEach(function (): void {
    LayoutBuilderInstallPackageAction::run();
});

it('installs article publishing page types layouts and widgets', function (): void {
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
        ->and(Widget::query()->where('key', 'article')->exists())->toBeTrue()
        ->and(Widget::query()->where('key', 'latest-articles')->exists())->toBeTrue()
        ->and(Widget::query()->where('key', 'archives')->exists())->toBeTrue()
        ->and(Widget::query()->where('key', 'tags')->exists())->toBeTrue()
        ->and(Widget::query()->where('key', 'related-pages')->exists())->toBeTrue();

    $articleType = Blueprint::query()->pageType()->where('key', BlogPageTypeEnum::Article->value)->firstOrFail();
    $articleLayout = Layout::query()->where('key', BlogLayoutEnum::Article->value)->firstOrFail();
    $latestArticlesWidget = Widget::query()->where('key', 'latest-articles')->firstOrFail();

    $articleContainers = blogTestArray($articleLayout->containers);

    expect($articleType->getMeta('with_next_prev'))->toBeTrue()
        ->and($articleType->getMeta('suppress_layout_neighbor_links'))->toBeTrue()
        ->and($latestArticlesWidget->component)->toBe(WidgetComponentEnum::PageLatest->value)
        ->and($latestArticlesWidget->is_livewire)->toBeFalse()
        ->and($articleContainers)->toHaveKey('latest')
        ->and(array_column(blogTestContainerWidgets($articleContainers, 'sidebar'), 'widget_key'))->not->toContain('latest-articles')
        ->and(array_column(blogTestContainerWidgets($articleContainers, 'latest'), 'widget_key'))->toContain('latest-articles');
});

it('updates default and results sidebars with article publishing widgets', function (): void {
    EnsureArticlePublishingDefaultsAction::run();

    $defaultLayout = blogTestLayout(Layout::query()->firstWhere('key', LayoutEnum::Default->value));
    $resultsLayout = blogTestLayout(Layout::query()->firstWhere('key', LayoutEnum::Results->value));

    $defaultContainers = blogTestArray($defaultLayout->getAttribute('containers'));
    $resultsContainers = blogTestArray($resultsLayout->getAttribute('containers'));

    expect($defaultContainers)->toBeArray()
        ->and($resultsContainers)->toBeArray();

    $defaultSidebarWidgetKeys = array_column(blogTestContainerWidgets($defaultContainers, 'sidebar'), 'widget_key');
    $resultsSidebarWidgetKeys = array_column(blogTestContainerWidgets($resultsContainers, 'sidebar'), 'widget_key');

    expect($defaultSidebarWidgetKeys)->toContain('latest-articles')
        ->and($defaultSidebarWidgetKeys)->not->toContain('latest-pages')
        ->and($resultsSidebarWidgetKeys)->toContain('latest-articles')
        ->and($resultsSidebarWidgetKeys)->toContain('archives')
        ->and($resultsSidebarWidgetKeys)->not->toContain('latest-pages');
});
