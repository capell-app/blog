<?php

declare(strict_types=1);

namespace Capell\Blog\Support\Creator;

use Capell\Admin\Filament\Configurators\Blueprints\PageBlueprintConfigurator;
use Capell\Admin\Filament\Configurators\Pages\ResultsPageConfigurator;
use Capell\Blog\Actions\EnsureArticlePublishingDefaultsAction;
use Capell\Blog\Actions\EnsureBlogPublishingSurfaceAction;
use Capell\Blog\Enums\BlogLayoutEnum;
use Capell\Blog\Enums\BlogPageTypeEnum;
use Capell\Blog\Enums\BlogTypeGroupEnum;
use Capell\Blog\Enums\LivewirePageComponentEnum;
use Capell\Blog\Enums\ResourceEnum;
use Capell\Blog\Enums\WidgetComponentEnum as BlogWidgetComponentEnum;
use Capell\Blog\Enums\WidgetConfiguratorEnum;
use Capell\Blog\Filament\Configurators\Articles\ArticlePageConfigurator;
use Capell\Blog\Filament\Configurators\Widgets\ArticleWidgetConfigurator;
use Capell\Blog\Models\Article;
use Capell\Core\Actions\SetupPageUrlsAction;
use Capell\Core\Enums\BlueprintGroupEnum;
use Capell\Core\Enums\BlueprintSubjectEnum;
use Capell\Core\Enums\LayoutEnum;
use Capell\Core\Enums\LayoutGroupEnum;
use Capell\Core\Enums\PageTypeEnum;
use Capell\Core\Enums\UrlParamTypeEnum;
use Capell\Core\Models\Blueprint;
use Capell\Core\Models\Language;
use Capell\Core\Models\Layout;
use Capell\Core\Models\Page;
use Capell\Core\Models\Site;
use Capell\Core\Support\Creator\BlueprintCreator;
use Capell\Core\Support\Creator\LayoutCreator;
use Capell\Frontend\Enums\RenderingStrategyEnum;
use Capell\LayoutBuilder\Enums\LayoutTypeEnum;
use Capell\LayoutBuilder\Enums\WidgetComponentEnum as LayoutWidgetComponentEnum;
use Capell\LayoutBuilder\Filament\Configurators\Types\WidgetTypeConfigurator;
use Capell\LayoutBuilder\Models\Widget;
use Capell\LayoutBuilder\Support\Creator\TypeCreator as LayoutTypeCreator;
use Capell\LayoutBuilder\Support\Creator\WidgetCreator;
use Capell\Navigation\Actions\AddPageToNavigationAction;
use Capell\Navigation\Models\Navigation;
use Filament\Support\Icons\Heroicon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Support\Collection;
use LogicException;

class BlogCreator
{
    public function setup(Site $site, bool $createWidgets = true): void
    {
        EnsureArticlePublishingDefaultsAction::run($createWidgets);
        EnsureBlogPublishingSurfaceAction::run($site, $site->getAllLanguages(), $createWidgets);
    }

    public function createTagPageType(): Blueprint
    {
        /** @var class-string<Blueprint> $typeMode */
        $typeMode = Blueprint::class;

        $blueprint = $typeMode::query()->firstOrCreate([
            'key' => BlogPageTypeEnum::Tag->value,
            'type' => BlueprintSubjectEnum::Page,
        ], [
            'name' => __('capell-blog::generic.tag_page'),
            'group' => BlueprintGroupEnum::System->value,
            'admin' => [
                'type_configurator' => PageBlueprintConfigurator::getKey(),
                'configurator' => ResultsPageConfigurator::getKey(),
                'icon' => 'heroicon-' . Heroicon::OutlinedTag->value,
                'required_fields' => ['title'],
            ],
            'meta' => [
                'accessible' => false,
                'component' => LivewirePageComponentEnum::TagPage,
                'livewire' => true,
                'limit' => 10,
                'listable' => false,
                'pagination' => true,
                'rendering_strategy' => RenderingStrategyEnum::FullLivewire->value,
                'url_params' => ['tag' => UrlParamTypeEnum::String->value],
                'with_date' => true,
                'with_image' => true,
                'with_summary' => true,
            ],
        ]);

        $blueprint->forceFill([
            'component' => LivewirePageComponentEnum::TagPage->value,
            'is_livewire' => true,
            'meta' => [
                ...($blueprint->meta ?? []),
                'accessible' => false,
                'component' => LivewirePageComponentEnum::TagPage->value,
                'livewire' => true,
                'limit' => 10,
                'listable' => false,
                'pagination' => true,
                'rendering_strategy' => RenderingStrategyEnum::FullLivewire->value,
                'url_params' => ['tag' => UrlParamTypeEnum::String->value],
                'with_date' => true,
                'with_image' => true,
                'with_summary' => true,
            ],
        ])->save();

        return $blueprint;
    }

    /**
     * @param  Collection<array-key, mixed>  $languages
     */
    public function createTagPage(Site $site, ?Page $parent = null, ?Collection $languages = null, ?Blueprint $type = null, ?Layout $layout = null): Page
    {
        $site->unsetRelation('siteDomains');
        $site->loadMissing(['language', 'siteDomains.language']);

        $type ??= $this->createTagPageType();
        $layout ??= $this->createTagResultsLayout();
        $languages ??= $site->getAllLanguages();
        $parent ??= $this->createTagsPage($site, $this->createBlogPage($site));

        $pageModel = Page::class;

        $page = $pageModel::query()->firstOrNew([
            'site_id' => $site->id,
            'blueprint_id' => $type->id,
            'parent_id' => $parent->getKey(),
        ], [
            'name' => __('capell-blog::generic.tag_page'),
        ]);

        $page->layout()->associate($layout);
        $page->meta = [
            ...($page->meta ?? []),
            'component' => LivewirePageComponentEnum::TagPage->value,
            'rendering_strategy' => RenderingStrategyEnum::FullLivewire->value,
        ];

        $page->save();

        $languages->each(function (Language $language) use ($page): void {
            $translation = $page->translations()->firstOrCreate([
                'language_id' => $language->id,
            ], [
                'title' => __('capell-blog::generic.tag_page_title'),
                'meta' => ['slug' => '*'],
            ]);
        });

        SetupPageUrlsAction::run($page);
        $page->load('pageUrl.siteDomain');

        return $page;
    }

    /**
     * @param  Collection<array-key, mixed>  $languages
     */
    public function createTagsPage(Site $site, ?Page $parent, ?Collection $languages = null, ?Blueprint $type = null, ?Layout $layout = null, bool $createWidgets = false): Page
    {
        $site->unsetRelation('siteDomains');
        $site->loadMissing(['language', 'siteDomains.language']);

        $type ??= $this->getPageType(PageTypeEnum::System);
        $layout ??= self::createTagsLayout();
        $languages ??= $site->getAllLanguages();

        if ($createWidgets) {
            $this->createTagsWidget($languages);
            $resultsWidgetType = resolve(LayoutTypeCreator::class)->resultsWidgetType();
            resolve(WidgetCreator::class)->latestPagesWidget($resultsWidgetType, $languages);
        }

        $pageModel = Page::class;

        $page = $pageModel::query()->firstOrNew([
            'layout_id' => $layout->id,
            'site_id' => $site->id,
            'blueprint_id' => $type->id,
            'parent_id' => $parent?->getKey(),
        ], [
            'name' => __('capell-blog::generic.tags_page'),
        ]);

        $page->save();

        $languages->each(function (Language $language) use ($page): void {
            $page->translations()->firstOrCreate([
                'language_id' => $language->id,
            ], [
                'title' => __('capell-blog::generic.tags_page_title'),
                'content' => '<p>' . __('capell-blog::generic.tags_page_description') . '</p>',
                'meta' => [
                    'label' => __('capell-blog::generic.tags'),
                    'slug' => 'tags',
                ],
            ]);
        });

        SetupPageUrlsAction::run($page);

        return $page;
    }

    /**
     * @param  array<array-key, mixed>  $keys
     * @param  Collection<int, Page>|array<array-key, mixed>  $pages
     * @param  Collection<int, Language>  $languages
     */
    public function addPagesToNavigations(array $keys, Site $site, Collection|array $pages, Collection $languages): void
    {
        Navigation::query()
            ->whereIn('key', $keys)
            ->where(
                fn (Builder $query) => $query->whereNull('site_id')
                    ->orWhere('site_id', $site->id),
            )
            ->where(
                fn (Builder $query) => $query->whereNull('language_id')
                    ->orWhereIn('language_id', $languages->pluck('id')),
            )
            ->get()
            ->each(function (Navigation $navigation) use ($pages): void {
                foreach ($pages as $page) {
                    AddPageToNavigationAction::run($page, $navigation);
                }
            });
    }

    /**
     * @param  Collection<array-key, mixed>  $languages
     */
    public function createArchivePage(
        Page $parent,
        ?Blueprint $type = null,
        ?Layout $layout = null,
        ?Collection $languages = null,
    ): Page {
        $site = $parent->site;

        throw_unless($site instanceof Site, LogicException::class, 'Archive page requires a parent site.');

        if (! $type instanceof Blueprint) {
            $type = Blueprint::query()->where('key', BlogPageTypeEnum::Archive)->pageType()->first()
                ?? self::createArchivePageType();
        }

        if (! $layout instanceof Layout) {
            $layout = Layout::query()->firstWhere('key', 'results') ?? resolve(LayoutCreator::class)->create(LayoutEnum::Results);
        }

        if (! $languages instanceof Collection) {
            $languages = $site->getAllLanguages();
        }

        $page = Page::query()->firstOrNew([
            'layout_id' => $layout->id,
            'site_id' => $site->id,
            'blueprint_id' => $type->id,
            'parent_id' => $parent->id,
        ]);

        $page->forceFill([
            'name' => __('capell-blog::generic.blog_archive_page'),
        ]);

        $page->save();

        $languages->each(function (Language $language) use ($page): void {
            $page->translations()->firstOrCreate([
                'language_id' => $language->id,
            ], [
                'title' => __('capell-blog::generic.blog_archive_title'),
                'meta' => [
                    'description' => __('capell-blog::generic.archive'),
                    'slug' => '*',
                ],
            ]);
        });

        SetupPageUrlsAction::run($page);

        return $page;
    }

    public function createArchivePageType(): Blueprint
    {
        $blueprint = Blueprint::query()->firstOrCreate([
            'key' => BlogPageTypeEnum::Archive->value,
            'type' => BlueprintSubjectEnum::Page,
        ], [
            'name' => __('capell-blog::generic.blog_archive_page'),
            'group' => BlueprintGroupEnum::System->value,
            'admin' => [
                'type_configurator' => PageBlueprintConfigurator::getKey(),
                'configurator' => ResultsPageConfigurator::getKey(),
                'icon' => 'heroicon-o-archive-box',
                'required_fields' => ['title'],
            ],
            'meta' => [
                'accessible' => false,
                'component' => LivewirePageComponentEnum::ArchivePage->value,
                'livewire' => true,
                'hidden_from_selection' => true,
                'limit' => 10,
                'listable' => false,
                'pagination' => true,
                'rendering_strategy' => RenderingStrategyEnum::FullLivewire->value,
                'url_params' => ['date' => UrlParamTypeEnum::String->value],
                'with_date' => true,
                'with_image' => false,
                'with_summary' => true,
            ],
        ]);

        $blueprint->forceFill([
            'component' => LivewirePageComponentEnum::ArchivePage->value,
            'name' => __('capell-blog::generic.blog_archive_page'),
            'group' => BlueprintGroupEnum::System->value,
            'is_livewire' => true,
            'admin' => [
                'type_configurator' => PageBlueprintConfigurator::getKey(),
                'configurator' => ResultsPageConfigurator::getKey(),
                'icon' => 'heroicon-o-archive-box',
                'required_fields' => ['title'],
            ],
            'meta' => [
                ...($blueprint->meta ?? []),
                'accessible' => false,
                'component' => LivewirePageComponentEnum::ArchivePage->value,
                'livewire' => true,
                'hidden_from_selection' => true,
                'limit' => 10,
                'listable' => false,
                'pagination' => true,
                'rendering_strategy' => RenderingStrategyEnum::FullLivewire->value,
                'url_params' => ['date' => UrlParamTypeEnum::String->value],
                'with_date' => true,
                'with_image' => false,
                'with_summary' => true,
            ],
        ])->save();

        return $blueprint;
    }

    public function createArchivesLayout(): Layout
    {
        $containers = [
            'main' => [
                'meta' => [
                    'colspan' => 9,
                ],
                'widgets' => [
                    ['widget_key' => 'breadcrumbs'],
                    ['widget_key' => 'archives', 'meta' => ['show_page_content' => true, 'show_page_title' => true]],
                ],
            ],
            'sidebar' => [
                'meta' => [
                    'colspan' => 3,
                    'override_columns' => 1,
                    'container' => 'full',
                    'padding' => ['md'],
                    'html_class' => 'sidebar-sticky space-y-8',
                ],
                'widgets' => [
                    ['widget_key' => 'latest-articles', 'meta' => ['hide_no_results' => true]],
                    ['widget_key' => 'tags', 'meta' => ['hide_no_results' => true]],
                ],
            ],
        ];

        $layout = Layout::query()->firstOrNew(['key' => BlogLayoutEnum::Archives->value]);

        $layout->forceFill([
            'name' => __('capell-blog::generic.archives'),
            'group' => LayoutGroupEnum::System->value,
            'containers' => $containers,
        ])->save();

        return $layout;
    }

    public function createBlogPageLayout(): Layout
    {
        $heroWidget = Widget::query()
            ->where('key', 'hero')
            ->first();
        $blogHeroWidget = null;

        if ($heroWidget instanceof Widget) {
            $blogHeroWidget = Widget::query()->firstOrNew(['key' => 'blog-hero']);
            $blogHeroWidget->forceFill([
                'name' => __('capell-blog::generic.blog_page'),
                'blueprint_id' => $heroWidget->blueprint_id,
                'component' => $heroWidget->component,
                'component_item' => $heroWidget->component_item,
                'is_livewire' => $heroWidget->is_livewire,
                'meta' => [
                    ...($heroWidget->meta ?? []),
                    'background_color' => '#f8fafc',
                    'carousel_arrows' => false,
                    'carousel_auto_play' => false,
                    'carousel_pagination' => false,
                    'height' => 'small',
                    'content_align' => 'left',
                    'content_width' => 'balanced',
                    'hero_background' => [
                        'mode' => 'custom',
                        'background_color' => '#f4f7fb',
                        'overlay_style' => 'mesh',
                        'overlay_opacity' => 0.2,
                        'accent_color' => '#315f8f',
                        'accent_color_alt' => '#8db9dc',
                    ],
                    'media_size' => 'compact',
                    'media_position' => 'right',
                ],
                'status' => true,
            ])->save();

            $blogHeroWidget->assets()->delete();
        }

        $pageContentWidget = Widget::query()->firstOrNew(['key' => 'blog-page-content']);
        $sourcePageContentWidget = Widget::query()->where('key', 'page-content')->first();

        if ($sourcePageContentWidget instanceof Widget) {
            $pageContentWidget->forceFill([
                'name' => __('capell-admin::generic.page_content'),
                'blueprint_id' => $sourcePageContentWidget->blueprint_id,
                'component' => $sourcePageContentWidget->component,
                'component_item' => $sourcePageContentWidget->component_item,
                'is_livewire' => $sourcePageContentWidget->is_livewire,
                'meta' => [
                    ...($sourcePageContentWidget->meta ?? []),
                    'page_content' => ['content'],
                    'show_page_title' => false,
                ],
                'status' => true,
            ])->save();
        }

        $hasHeroWidget = $blogHeroWidget instanceof Widget;
        $pageContentWidget = $hasHeroWidget && $pageContentWidget->exists
            ? ['widget_key' => $pageContentWidget->key]
            : ['widget_key' => 'page-content'];

        $containers = [
            'main' => [
                'meta' => [
                    'colspan' => 9,
                ],
                'widgets' => [
                    ['widget_key' => 'breadcrumbs'],
                    $pageContentWidget,
                    ['widget_key' => 'page-slot'],
                ],
            ],
            'sidebar' => [
                'meta' => [
                    'colspan' => 3,
                    'override_columns' => 1,
                    'container' => 'full',
                    'padding' => ['t-sm'],
                    'html_class' => 'sidebar-sticky space-y-8',
                ],
                'widgets' => [
                    ['widget_key' => 'popular-articles', 'meta' => ['hide_no_results' => true]],
                    ['widget_key' => 'tags', 'meta' => ['hide_no_results' => true]],
                    ['widget_key' => 'archives', 'meta' => ['hide_no_results' => true]],
                ],
            ],
        ];

        if ($hasHeroWidget) {
            $containers = [
                'hero' => [
                    'meta' => [
                        'colspan' => 12,
                        'container' => 'full',
                    ],
                    'widgets' => [
                        ['widget_key' => $blogHeroWidget->key],
                    ],
                ],
                ...$containers,
            ];
        }

        $layout = Layout::query()->firstOrNew(['key' => BlogLayoutEnum::BlogPage->value]);

        $layout->forceFill([
            'name' => __('capell-blog::generic.blog_page'),
            'group' => LayoutGroupEnum::System->value,
            'containers' => $containers,
        ])->save();

        return $layout;
    }

    public function createTagsLayout(): Layout
    {
        $containers = [
            'main' => [
                'meta' => [
                    'colspan' => 9,
                ],
                'widgets' => [
                    ['widget_key' => 'breadcrumbs'],
                    ['widget_key' => 'tags', 'meta' => ['show_page_title' => true, 'show_page_content' => true]],
                ],
            ],
            'sidebar' => [
                'meta' => [
                    'colspan' => 3,
                    'override_columns' => 1,
                    'container' => 'full',
                    'padding' => ['md'],
                    'html_class' => 'sidebar-sticky space-y-8',
                ],
                'widgets' => [
                    ['widget_key' => 'latest-pages', 'meta' => ['hide_no_results' => true]],
                ],
            ],
        ];

        return Layout::query()->firstOrCreate(['key' => BlogLayoutEnum::Tags->value], [
            'name' => __('capell-blog::generic.tags'),
            'group' => LayoutGroupEnum::System->value,
            'containers' => $containers,
        ]);
    }

    public function createTagResultsLayout(): Layout
    {
        $containers = [
            'main' => [
                'meta' => [
                    'colspan' => 9,
                ],
                'widgets' => [
                    ['widget_key' => 'breadcrumbs'],
                    ['widget_key' => 'page-content'],
                    ['widget_key' => 'page-slot'],
                ],
            ],
            'sidebar' => [
                'meta' => [
                    'colspan' => 3,
                    'override_columns' => 1,
                    'container' => 'full',
                    'padding' => ['md'],
                    'html_class' => 'sidebar-sticky space-y-8',
                ],
                'widgets' => [
                    ['widget_key' => 'latest-articles', 'meta' => ['hide_no_results' => true]],
                    ['widget_key' => 'tags', 'meta' => ['hide_no_results' => true]],
                    ['widget_key' => 'archives', 'meta' => ['hide_no_results' => true]],
                ],
            ],
            'footer' => [
                'meta' => [
                    'colspan' => 12,
                    'container' => 'lg',
                    'margin' => ['t-xl'],
                    'padding' => ['t-lg', 'b-xl'],
                    'html_class' => 'blog-tag-footer',
                ],
                'widgets' => [
                    ['widget_key' => 'latest-articles', 'meta' => ['hide_no_results' => true]],
                ],
            ],
        ];

        $layout = Layout::query()->firstOrNew(['key' => BlogLayoutEnum::TagResults->value]);

        $layout->forceFill([
            'name' => __('capell-blog::generic.tag_results'),
            'group' => LayoutGroupEnum::System->value,
            'containers' => $containers,
        ])->save();

        return $layout;
    }

    /**
     * @param  Collection<array-key, mixed>  $languages
     */
    public function createArchivesWidget(?Collection $languages = null): Widget
    {
        if (! $languages instanceof Collection) {
            $languages = Language::all();
        }

        $typeCreator = resolve(LayoutTypeCreator::class);
        $type = $typeCreator->resultsWidgetType();

        $widget = Widget::query()->firstOrCreate([
            'key' => 'archives',
        ], [
            'name' => __('capell-blog::generic.article_archives'),
            'blueprint_id' => $type->id,
            'meta' => [
                'component' => BlogWidgetComponentEnum::Archives,
                'page_group' => strtolower(ResourceEnum::Article->name),
                'pagination' => true,
                'with_image' => false,
                'with_date' => true,
                'with_link_text' => true,
                'with_summary' => true,
                'margin' => ['b-lg'],
            ],
        ]);

        $widget->forceFill([
            'component' => BlogWidgetComponentEnum::Archives->value,
            'is_livewire' => false,
        ])->save();

        $languages->each(function (Language $language) use ($widget): void {
            $widget->translations()->firstOrCreate([
                'language_id' => $language->id,
            ], [
                'title' => __('capell-blog::generic.archives'),
                'meta' => [
                    'no_results' => __('capell-blog::messages.no_archives_found'),
                ],
            ]);
        });

        return $widget;
    }

    /**
     * @param  Collection<array-key, mixed>  $languages
     */
    public function createTagsWidget(Collection $languages): void
    {
        $widgetModel = Widget::class;

        $typeCreator = resolve(LayoutTypeCreator::class);
        $type = $typeCreator->resultsWidgetType();

        $widget = $widgetModel::query()->firstOrCreate([
            'key' => 'tags',
        ], [
            'name' => __('capell-blog::generic.tags'),
            'blueprint_id' => $type->id,
            'meta' => [
                'component' => BlogWidgetComponentEnum::Tags,
                'page_model' => Relation::getMorphAlias(Article::class),
                'size' => 'sm',
            ],
            'admin' => [
                'icon' => 'heroicon-' . Heroicon::OutlinedTag->value,
            ],
        ]);

        $widget->forceFill([
            'component' => BlogWidgetComponentEnum::Tags->value,
            'is_livewire' => false,
        ])->save();

        $languages->each(function (Language $language) use ($widget): void {
            $widget->translations()->firstOrCreate([
                'language_id' => $language->id,
            ], [
                'title' => __('capell-blog::generic.tags'),
                'meta' => [
                    'no_results' => __('capell-blog::messages.no_tags_found'),
                ],
            ]);
        });
    }

    /**
     * @param  Collection<array-key, mixed>  $languages
     */
    public function createArchivesPage(
        Page $parent,
        ?Blueprint $type = null,
        ?Layout $layout = null,
        ?Collection $languages = null,
    ): Page {
        $site = $parent->site;

        throw_unless($site instanceof Site, LogicException::class, 'Archives page requires a parent site.');

        if (! $layout instanceof Layout) {
            $layout = Layout::query()->firstWhere('key', 'archives') ?? self::createArchivesLayout();
        }

        if (! $type instanceof Blueprint) {
            $type = Blueprint::query()->where('key', 'system')->pageType()->first()
                ?? resolve(BlueprintCreator::class)->systemPageType();
        }

        if (! $languages instanceof Collection) {
            $languages = $site->languages;
        }

        $page = Page::query()->firstOrNew([
            'layout_id' => $layout->id,
            'site_id' => $site->id,
            'blueprint_id' => $type->id,
            'parent_id' => $parent->id,
        ]);

        $page->forceFill([
            'name' => __('capell-blog::generic.blog_archives_page'),
        ]);

        $page->save();

        $languages->each(function (Language $language) use ($page): void {
            $page->translations()->firstOrCreate([
                'language_id' => $language->id,
            ], [
                'title' => __('capell-blog::generic.archives'),
                'content' => sprintf('<p>%s</p>', __('capell-blog::generic.blog_archives_description')),
                'meta' => [
                    'title' => __('capell-blog::generic.blog_archives_title'),
                    'description' => __('capell-blog::generic.archives'),
                    'slug' => str(__('capell-blog::generic.archives'))->slug(),
                ],
            ]);
        });

        SetupPageUrlsAction::run($page);

        return $page;
    }

    public function createArticleLayout(bool $createWidgets = true): Layout
    {
        if ($createWidgets) {
            $languages = Language::all();
            $widgetCreator = resolve(WidgetCreator::class);
            $typeCreator = resolve(LayoutTypeCreator::class);
            $systemWidgetType = $typeCreator->systemWidgetType();
            $pageContentWidgetType = $typeCreator->pageContentWidgetType();
            $resultsType = $typeCreator->resultsWidgetType();

            $widgetCreator->breadcrumbWidget($systemWidgetType);
            $widgetCreator->pageSlotWidget($systemWidgetType);
            $widgetCreator->pageContentWidget($pageContentWidgetType);

            $articleType = $this->createArticleWidgetType();
            $this->createArticleWidget($articleType);

            $this->createLatestArticlesWidget($languages);
            $this->relatedArticlesWidget($resultsType, $languages);
            $this->createTagsWidget($languages);
            $this->createArchivesWidget($languages);
        }

        $containers = [
            'main' => [
                'meta' => [
                    'colspan' => 9,
                ],
                'widgets' => [
                    ['widget_key' => 'breadcrumbs'],
                    ['widget_key' => 'article'],
                ],
            ],
            'sidebar' => [
                'meta' => [
                    'colspan' => 3,
                    'override_columns' => 1,
                    'container' => 'full',
                    'padding' => ['md'],
                    'html_class' => 'sidebar-sticky space-y-8',
                ],
                'widgets' => [
                    ['widget_key' => 'tags', 'meta' => ['hide_no_results' => true]],
                    ['widget_key' => 'archives', 'meta' => ['hide_no_results' => true]],
                ],
            ],
            'latest' => [
                'meta' => [
                    'colspan' => 12,
                    'container' => 'lg',
                    'margin' => ['t-xl'],
                    'padding' => ['t-lg', 'b-xl'],
                    'html_class' => 'blog-latest-articles',
                ],
                'widgets' => [
                    ['widget_key' => 'latest-articles', 'meta' => ['hide_no_results' => true]],
                ],
            ],
        ];

        $layout = Layout::query()->firstOrCreate(['key' => BlogLayoutEnum::Article->value], [
            'name' => __('capell-blog::generic.article'),
            'group' => LayoutGroupEnum::Default->value,
            'containers' => $containers,
        ]);

        $mergedContainers = $this->withArticleLatestArticlesContainer($layout->containers, $containers);

        $layout->forceFill([
            'name' => __('capell-blog::generic.article'),
            'group' => LayoutGroupEnum::Default->value,
            'containers' => $mergedContainers,
        ])->save();

        return $layout;
    }

    public function createArticlePageType(): Blueprint
    {
        $blueprint = Blueprint::query()->firstOrCreate([
            'key' => BlogPageTypeEnum::Article->value,
            'type' => BlueprintSubjectEnum::Page,
        ], [
            'name' => __('capell-blog::generic.article'),
            'group' => BlogTypeGroupEnum::Article->value,
            'admin' => [
                'icon' => 'heroicon-o-newspaper',
                'type_configurator' => PageBlueprintConfigurator::getKey(),
                'configurator' => ArticlePageConfigurator::getKey(),
                'resource' => strtolower(ResourceEnum::Article->name),
                'required_fields' => ['title'],
            ],
            'meta' => [
                'suppress_layout_neighbor_links' => true,
                'with_next_prev' => true,
            ],
        ]);

        $blueprint->forceFill([
            'meta' => [
                ...($blueprint->meta ?? []),
                'suppress_layout_neighbor_links' => true,
                'with_next_prev' => true,
            ],
        ])->save();

        return $blueprint;
    }

    public function createArticleWidget(Blueprint $type): Widget
    {
        $widget = Widget::query()->firstOrCreate([
            'key' => 'article',
        ], [
            'name' => __('capell-blog::generic.article'),
            'blueprint_id' => $type->id,
            'meta' => [
                'with_date' => true,
                'with_author' => true,
                'with_next_prev' => true,
            ],
        ]);

        $widget->forceFill([
            'component' => BlogWidgetComponentEnum::Article->value,
            'is_livewire' => false,
        ])->save();

        return $widget;
    }

    /**
     * @param  Collection<array-key, mixed>  $languages
     */
    public function relatedArticlesWidget(?Blueprint $type = null, ?Collection $languages = null): Widget
    {
        if (! $type instanceof Blueprint) {
            $typeCreator = resolve(LayoutTypeCreator::class);
            $type = $typeCreator->resultsWidgetType();
        }

        if (! $languages instanceof Collection) {
            $languages = Language::all();
        }

        $widget = Widget::query()->firstOrCreate([
            'key' => 'related-pages',
        ], [
            'name' => __('capell-admin::generic.related_pages'),
            'blueprint_id' => $type->id,
            'meta' => [
                'component' => BlogWidgetComponentEnum::PageRelated,
                'limit' => 6,
                'pagination' => false,
                'page_model' => Relation::getMorphAlias(Article::class),
                'exclude_types' => ['home'],
                'exclude_parent' => true,
                'with_summary' => true,
                'with_link_text' => true,
                'with_image' => false,
                'columns' => 1,
            ],
            'admin' => [
                'icon' => 'heroicon-c-link',
                'type_configurator' => WidgetTypeConfigurator::getKey(),
                'configurator' => WidgetConfiguratorEnum::Related->name,
            ],
        ]);

        $widget->forceFill([
            'component' => BlogWidgetComponentEnum::PageRelated->value,
            'is_livewire' => false,
        ])->save();

        $languages->each(function (Language $language) use ($widget): void {
            $widget->translations()->firstOrCreate([
                'language_id' => $language->id,
            ], [
                'title' => __('capell-layout-builder::heading.related_pages'),
            ]);
        });

        return $widget;
    }

    public function createArticleWidgetType(): Blueprint
    {
        return Blueprint::query()->firstOrCreate([
            'key' => 'article',
            'type' => LayoutTypeEnum::Widget,
        ], [
            'name' => __('capell-blog::generic.article'),
            'group' => BlueprintGroupEnum::System->value,
            'admin' => [
                'type_configurator' => PageBlueprintConfigurator::getKey(),
                'configurator' => ArticleWidgetConfigurator::getKey(),
                'icon' => 'heroicon-o-newspaper',
            ],
            'meta' => [
                'component' => BlogWidgetComponentEnum::Article,
                'margin' => ['xl'],
            ],
        ]);
    }

    /**
     * @param  Collection<array-key, mixed>  $languages
     * @param  array<array-key, mixed>  $meta
     */
    public function createBlogPage(
        Site $site,
        ?Blueprint $type = null,
        ?Layout $layout = null,
        ?Collection $languages = null,
        array $meta = [],
    ): Page {
        $site->unsetRelation('siteDomains');
        $site->loadMissing(['language', 'siteDomains.language']);

        if (! $type instanceof Blueprint) {
            $type = self::createBlogPageType();
        }

        if (! $layout instanceof Layout) {
            $layout = self::createBlogPageLayout();
        }

        if (! $languages instanceof Collection) {
            $languages = $site->languages;
        }

        $page = Page::query()
            ->where('site_id', $site->id)
            ->where('blueprint_id', $type->id)
            ->first()
            ?? $this->existingBlogPageForSite($site)
            ?? new Page([
                'site_id' => $site->id,
                'blueprint_id' => $type->id,
            ]);

        $page->mergeMeta([
            ...$meta,
            'component' => LivewirePageComponentEnum::BlogPage->value,
            'rendering_strategy' => RenderingStrategyEnum::FullLivewire->value,
            'with_image' => true,
        ]);

        $page->forceFill([
            'blueprint_id' => $type->id,
            'layout_id' => $layout->id,
            'name' => __('capell-blog::generic.blog'),
        ]);

        $page->save();

        $languages->each(function (Language $language) use ($page): void {
            $translation = $page->translations()->firstOrNew([
                'language_id' => $language->id,
            ]);

            $translation->forceFill([
                'title' => __('capell-blog::generic.blog'),
                'content' => null,
                'meta' => [
                    ...($translation->meta ?? []),
                    'hero_title' => __('capell-blog::generic.blog'),
                    'label' => __('capell-blog::generic.blog'),
                    'no_results' => __('capell-blog::messages.no_articles_found'),
                    'slug' => 'blog',
                ],
            ])->save();
        });

        SetupPageUrlsAction::run($page);

        return $page;
    }

    public function createBlogPageType(): Blueprint
    {
        $blueprint = Blueprint::query()->firstOrCreate([
            'key' => BlogPageTypeEnum::Blog->value,
            'type' => BlueprintSubjectEnum::Page,
        ], [
            'name' => __('capell-blog::generic.blog'),
            'group' => BlueprintGroupEnum::Results->value,
            'admin' => [
                'type_configurator' => PageBlueprintConfigurator::getKey(),
                'configurator' => ResultsPageConfigurator::getKey(),
                'icon' => 'heroicon-o-newspaper',
                'exclude_parent' => true,
                'required_fields' => ['title'],
            ],
            'meta' => [
                'component' => LivewirePageComponentEnum::BlogPage,
                'livewire' => true,
                'exclude_parent' => true,
                'limit' => 10,
                'columns' => 3,
                'listable' => false,
                'page_group' => strtolower(ResourceEnum::Article->name),
                'pagination' => true,
                'rendering_strategy' => RenderingStrategyEnum::FullLivewire->value,
                'sitemap' => true,
                'url_params' => ['page' => UrlParamTypeEnum::Int->value],
                'with_date' => true,
                'with_image' => false,
                'with_summary' => true,
            ],
        ]);

        $blueprint->forceFill([
            'component' => LivewirePageComponentEnum::BlogPage->value,
            'is_livewire' => true,
            'meta' => [
                ...($blueprint->meta ?? []),
                'component' => LivewirePageComponentEnum::BlogPage->value,
                'livewire' => true,
                'exclude_parent' => true,
                'limit' => 10,
                'columns' => 3,
                'listable' => false,
                'page_group' => strtolower(ResourceEnum::Article->name),
                'pagination' => true,
                'rendering_strategy' => RenderingStrategyEnum::FullLivewire->value,
                'sitemap' => true,
                'url_params' => ['page' => UrlParamTypeEnum::Int->value],
                'with_date' => true,
                'with_image' => false,
                'with_summary' => true,
            ],
        ])->save();

        return $blueprint;
    }

    /**
     * @param  Collection<array-key, mixed>  $languages
     */
    public function createLatestArticlesWidget(?Collection $languages = null): Widget
    {
        return $this->createArticlesListWidget(
            key: 'latest-articles',
            title: __('capell-blog::generic.latest_articles'),
            languages: $languages,
            withDate: true,
            withImage: true,
        );
    }

    /**
     * @param  Collection<array-key, mixed>  $languages
     */
    public function createPopularArticlesWidget(?Collection $languages = null): Widget
    {
        return $this->createArticlesListWidget(
            key: 'popular-articles',
            title: __('capell-blog::generic.popular_articles'),
            languages: $languages,
            withDate: false,
            withImage: true,
        );
    }

    private function existingBlogPageForSite(Site $site): ?Page
    {
        return Page::query()
            ->where('site_id', $site->id)
            ->whereHas('pageUrls', function (Builder $query): void {
                $query
                    ->where('url', '/blog')
                    ->where('status', true);
            })
            ->first();
    }

    /**
     * @param  Collection<array-key, mixed>|null  $languages
     */
    private function createArticlesListWidget(
        string $key,
        string $title,
        ?Collection $languages = null,
        bool $withDate = true,
        bool $withImage = true,
    ): Widget {
        if (! $languages instanceof Collection) {
            $languages = Language::all();
        }

        $typeCreator = resolve(LayoutTypeCreator::class);
        $type = $typeCreator->resultsWidgetType();

        $widget = Widget::query()->firstOrCreate([
            'key' => $key,
        ], [
            'name' => $title,
            'blueprint_id' => $type->id,
            'meta' => [
                'component' => LayoutWidgetComponentEnum::PageLatest,
                'livewire' => false,
                'limit' => 5,
                'page_model' => Relation::getMorphAlias(Article::class),
                'page_group' => strtolower(ResourceEnum::Article->name),
                'pagination' => false,
                'with_date' => $withDate,
                'with_image' => $withImage,
                'with_summary' => true,
                'with_link_text' => true,
                'margin' => ['b-lg'],
            ],
            'admin' => [
                'icon' => 'heroicon-o-newspaper',
            ],
        ]);

        $widget->forceFill([
            'name' => $title,
            'blueprint_id' => $type->id,
            'component' => LayoutWidgetComponentEnum::PageLatest->value,
            'is_livewire' => false,
            'meta' => [
                ...($widget->meta ?? []),
                'component' => LayoutWidgetComponentEnum::PageLatest->value,
                'livewire' => false,
                'limit' => 5,
                'page_model' => Relation::getMorphAlias(Article::class),
                'page_group' => strtolower(ResourceEnum::Article->name),
                'pagination' => false,
                'with_date' => $withDate,
                'with_image' => $withImage,
                'with_summary' => true,
                'with_link_text' => true,
                'margin' => ['b-lg'],
            ],
        ])->save();

        foreach ($languages as $language) {
            $widget->translations()->firstOrCreate([
                'language_id' => $language->id,
            ], [
                'title' => $title,
            ]);
        }

        return $widget;
    }

    private function getPageType(string|PageTypeEnum $key): Blueprint
    {
        $typeModel = Blueprint::class;

        $type = $typeModel::query()->where('key', $key)->pageType()->first();

        if ($type instanceof Blueprint) {
            return $type;
        }

        if ($key instanceof PageTypeEnum) {
            $key = $key->value;
        }

        $createdType = resolve(BlueprintCreator::class)->createPageType($key);

        if ($createdType instanceof Blueprint) {
            return $createdType;
        }

        throw new LogicException('Expected page type creator to return a Blueprint model.');
    }

    /**
     * @param  array<string, array<array-key, mixed>>|null  $currentContainers
     * @param  array<string, array<array-key, mixed>>  $defaultContainers
     * @return array<string, array<array-key, mixed>>
     */
    private function withArticleLatestArticlesContainer(?array $currentContainers, array $defaultContainers): array
    {
        $containers = $currentContainers !== null && $currentContainers !== []
            ? $currentContainers
            : $defaultContainers;

        $containers['main'] = $defaultContainers['main'];

        if (isset($containers['sidebar']['widgets']) && is_array($containers['sidebar']['widgets'])) {
            $containers['sidebar']['widgets'] = collect($containers['sidebar']['widgets'])
                ->reject(fn (array $widget): bool => ($widget['widget_key'] ?? null) === 'latest-articles')
                ->values()
                ->all();
        }

        $containers['latest'] = $defaultContainers['latest'];

        return $containers;
    }
}
