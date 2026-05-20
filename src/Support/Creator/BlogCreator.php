<?php

declare(strict_types=1);

namespace Capell\Blog\Support\Creator;

use Capell\Admin\Filament\Configurators\Pages\ResultsPageConfigurator;
use Capell\Admin\Filament\Configurators\Types\PageTypeConfigurator;
use Capell\Blog\Actions\EnsureArticlePublishingDefaultsAction;
use Capell\Blog\Actions\EnsureBlogPublishingSurfaceAction;
use Capell\Blog\Enums\BlockComponentEnum as BlogBlockComponentEnum;
use Capell\Blog\Enums\BlockConfiguratorEnum;
use Capell\Blog\Enums\BlogLayoutEnum;
use Capell\Blog\Enums\BlogPageTypeEnum;
use Capell\Blog\Enums\BlogTypeGroupEnum;
use Capell\Blog\Enums\LivewirePageComponentEnum;
use Capell\Blog\Enums\ResourceEnum;
use Capell\Blog\Filament\Configurators\Articles\ArticlePageConfigurator;
use Capell\Blog\Filament\Configurators\Blocks\ArticleBlockConfigurator;
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
use Capell\LayoutBuilder\Enums\BlockComponentEnum as LayoutBlockComponentEnum;
use Capell\LayoutBuilder\Enums\LayoutTypeEnum;
use Capell\LayoutBuilder\Filament\Configurators\Types\BlockTypeConfigurator;
use Capell\LayoutBuilder\Models\Block;
use Capell\LayoutBuilder\Support\Creator\BlockCreator;
use Capell\LayoutBuilder\Support\Creator\TypeCreator as LayoutTypeCreator;
use Capell\Navigation\Actions\AddPageToNavigationAction;
use Capell\Navigation\Models\Navigation;
use Filament\Support\Icons\Heroicon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Support\Collection;
use LogicException;

class BlogCreator
{
    public function setup(Site $site, bool $createBlocks = true): void
    {
        EnsureArticlePublishingDefaultsAction::run($createBlocks);
        EnsureBlogPublishingSurfaceAction::run($site, $site->getAllLanguages(), $createBlocks);
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
                'type_configurator' => PageTypeConfigurator::getKey(),
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
                'with_image' => false,
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
                'with_image' => false,
                'with_summary' => true,
            ],
        ])->save();

        return $blueprint;
    }

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
            'parent_id' => $parent?->getKey(),
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

        return $page;
    }

    public function createTagsPage(Site $site, ?Page $parent, ?Collection $languages = null, ?Blueprint $type = null, ?Layout $layout = null, bool $createBlocks = false): Page
    {
        $site->unsetRelation('siteDomains');
        $site->loadMissing(['language', 'siteDomains.language']);

        $type ??= $this->getPageType(PageTypeEnum::System);
        $layout ??= self::createTagsLayout();
        $languages ??= $site->getAllLanguages();

        if ($createBlocks) {
            $this->createTagsBlock($languages);
            $resultsBlockType = resolve(LayoutTypeCreator::class)->resultsBlockType();
            resolve(BlockCreator::class)->latestPagesBlock($resultsBlockType, $languages);
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

    public function createArchivePage(
        Page $parent,
        ?Blueprint $type = null,
        ?Layout $layout = null,
        ?Collection $languages = null,
    ): Page {
        $site = $parent->site;

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
                'type_configurator' => PageTypeConfigurator::getKey(),
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
                'type_configurator' => PageTypeConfigurator::getKey(),
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
                'blocks' => [
                    ['block_key' => 'breadcrumbs'],
                    ['block_key' => 'archives', 'meta' => ['show_page_content' => true, 'show_page_title' => true]],
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
                'blocks' => [
                    ['block_key' => 'latest-articles', 'meta' => ['hide_no_results' => true]],
                    ['block_key' => 'tags', 'meta' => ['hide_no_results' => true]],
                ],
            ],
        ];

        return Layout::query()->firstOrCreate(['key' => BlogLayoutEnum::Archives->value], [
            'name' => __('capell-blog::generic.archives'),
            'group' => LayoutGroupEnum::System->value,
            'containers' => $containers,
            'blocks' => $this->blockKeys($containers),
        ]);
    }

    public function createBlogPageLayout(): Layout
    {
        $containers = [
            'main' => [
                'meta' => [
                    'colspan' => 9,
                ],
                'blocks' => [
                    ['block_key' => 'breadcrumbs'],
                    ['block_key' => 'page-content', 'meta' => ['show_page_title' => true]],
                    ['block_key' => 'page-slot'],
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
                'blocks' => [
                    ['block_key' => 'tags', 'meta' => ['hide_no_results' => true]],
                    ['block_key' => 'archives', 'meta' => ['hide_no_results' => true]],
                ],
            ],
        ];

        return Layout::query()->firstOrCreate(['key' => BlogLayoutEnum::BlogPage->value], [
            'name' => __('capell-blog::generic.blog_page'),
            'group' => LayoutGroupEnum::System->value,
            'containers' => $containers,
            'blocks' => $this->blockKeys($containers),
        ]);
    }

    public function createTagsLayout(): Layout
    {
        $containers = [
            'main' => [
                'meta' => [
                    'colspan' => 9,
                ],
                'blocks' => [
                    ['block_key' => 'breadcrumbs'],
                    ['block_key' => 'tags', 'meta' => ['show_page_title' => true, 'show_page_content' => true]],
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
                'blocks' => [
                    ['block_key' => 'latest-pages', 'meta' => ['hide_no_results' => true]],
                ],
            ],
        ];

        return Layout::query()->firstOrCreate(['key' => BlogLayoutEnum::Tags->value], [
            'name' => __('capell-blog::generic.tags'),
            'group' => LayoutGroupEnum::System->value,
            'containers' => $containers,
            'blocks' => $this->blockKeys($containers),
        ]);
    }

    public function createTagResultsLayout(): Layout
    {
        $containers = [
            'main' => [
                'meta' => [
                    'colspan' => 12,
                ],
                'blocks' => [
                    ['block_key' => 'breadcrumbs'],
                    ['block_key' => 'page-content'],
                    ['block_key' => 'page-slot'],
                ],
            ],
        ];

        return Layout::query()->firstOrCreate(['key' => BlogLayoutEnum::TagResults->value], [
            'name' => __('capell-blog::generic.tag_results'),
            'group' => LayoutGroupEnum::System->value,
            'containers' => $containers,
            'blocks' => $this->blockKeys($containers),
        ]);
    }

    public function createArchivesBlock(?Collection $languages = null): Block
    {
        if (! $languages instanceof Collection) {
            $languages = Language::all();
        }

        $typeCreator = resolve(LayoutTypeCreator::class);
        $type = $typeCreator->resultsBlockType();

        $block = Block::query()->firstOrCreate([
            'key' => 'archives',
        ], [
            'name' => __('capell-blog::generic.article_archives'),
            'blueprint_id' => $type->id,
            'meta' => [
                'component' => BlogBlockComponentEnum::Archives,
                'page_group' => strtolower(ResourceEnum::Article->name),
                'pagination' => true,
                'with_image' => false,
                'with_date' => true,
                'with_link_text' => true,
                'with_summary' => true,
                'margin' => ['b-lg'],
            ],
        ]);

        $block->forceFill([
            'component' => BlogBlockComponentEnum::Archives->value,
            'is_livewire' => false,
        ])->save();

        $languages->each(function (Language $language) use ($block): void {
            $block->translations()->firstOrCreate([
                'language_id' => $language->id,
            ], [
                'title' => __('capell-blog::generic.archives'),
                'meta' => [
                    'no_results' => __('capell-blog::messages.no_archives_found'),
                ],
            ]);
        });

        return $block;
    }

    public function createTagsBlock(Collection $languages): void
    {
        $blockModel = Block::class;

        $typeCreator = resolve(LayoutTypeCreator::class);
        $type = $typeCreator->resultsBlockType();

        $block = $blockModel::query()->firstOrCreate([
            'key' => 'tags',
        ], [
            'name' => __('capell-blog::generic.tags'),
            'blueprint_id' => $type->id,
            'meta' => [
                'component' => BlogBlockComponentEnum::Tags,
                'page_model' => Relation::getMorphAlias(Article::class),
                'size' => 'sm',
            ],
            'admin' => [
                'icon' => 'heroicon-' . Heroicon::OutlinedTag->value,
            ],
        ]);

        $block->forceFill([
            'component' => BlogBlockComponentEnum::Tags->value,
            'is_livewire' => false,
        ])->save();

        $languages->each(function (Language $language) use ($block): void {
            $block->translations()->firstOrCreate([
                'language_id' => $language->id,
            ], [
                'title' => __('capell-blog::generic.tags'),
                'meta' => [
                    'no_results' => __('capell-blog::messages.no_tags_found'),
                ],
            ]);
        });
    }

    public function createArchivesPage(
        Page $parent,
        ?Blueprint $type = null,
        ?Layout $layout = null,
        ?Collection $languages = null,
    ): Page {
        $site = $parent->site;
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

    public function createArticleLayout(bool $createBlocks = true): Layout
    {
        if ($createBlocks) {
            $languages = Language::all();
            $blockCreator = resolve(BlockCreator::class);
            $typeCreator = resolve(LayoutTypeCreator::class);
            $systemBlockType = $typeCreator->systemBlockType();
            $pageContentBlockType = $typeCreator->pageContentBlockType();
            $resultsType = $typeCreator->resultsBlockType();

            $blockCreator->breadcrumbBlock($systemBlockType);
            $blockCreator->pageSlotBlock($systemBlockType);
            $blockCreator->pageContentBlock($pageContentBlockType);

            $articleType = $this->createArticleBlockType();
            $this->createArticleBlock($articleType);

            $this->createLatestArticlesBlock($languages);
            $this->relatedArticlesBlock($resultsType, $languages);
            $this->createTagsBlock($languages);
            $this->createArchivesBlock($languages);
        }

        $containers = [
            'main' => [
                'meta' => [
                    'colspan' => 9,
                ],
                'blocks' => [
                    ['block_key' => 'breadcrumbs'],
                    ['block_key' => 'article'],
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
                'blocks' => [
                    ['block_key' => 'tags', 'meta' => ['hide_no_results' => true]],
                    ['block_key' => 'archives', 'meta' => ['hide_no_results' => true]],
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
                'blocks' => [
                    ['block_key' => 'latest-articles', 'meta' => ['hide_no_results' => true]],
                ],
            ],
        ];

        $layout = Layout::query()->firstOrCreate(['key' => BlogLayoutEnum::Article->value], [
            'name' => __('capell-blog::generic.article'),
            'group' => LayoutGroupEnum::Default->value,
            'containers' => $containers,
            'blocks' => $this->blockKeys($containers),
        ]);

        $mergedContainers = $this->withArticleLatestArticlesContainer($layout->containers, $containers);

        $layout->forceFill([
            'containers' => $mergedContainers,
            'blocks' => $this->blockKeys($mergedContainers),
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
                'type_configurator' => PageTypeConfigurator::getKey(),
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

    public function createArticleBlock(Blueprint $type): Block
    {
        $block = Block::query()->firstOrCreate([
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

        $block->forceFill([
            'component' => BlogBlockComponentEnum::Article->value,
            'is_livewire' => false,
        ])->save();

        return $block;
    }

    public function relatedArticlesBlock(?Blueprint $type = null, ?Collection $languages = null): Block
    {
        if (! $type instanceof Blueprint) {
            $typeCreator = resolve(LayoutTypeCreator::class);
            $type = $typeCreator->resultsBlockType();
        }

        if (! $languages instanceof Collection) {
            $languages = Language::all();
        }

        $block = Block::query()->firstOrCreate([
            'key' => 'related-pages',
        ], [
            'name' => __('capell-admin::generic.related_pages'),
            'blueprint_id' => $type->id,
            'meta' => [
                'component' => BlogBlockComponentEnum::PageRelated,
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
                'type_configurator' => BlockTypeConfigurator::getKey(),
                'configurator' => BlockConfiguratorEnum::Related->name,
            ],
        ]);

        $block->forceFill([
            'component' => BlogBlockComponentEnum::PageRelated->value,
            'is_livewire' => false,
        ])->save();

        $languages->each(function (Language $language) use ($block): void {
            $block->translations()->firstOrCreate([
                'language_id' => $language->id,
            ], [
                'title' => __('capell-layout-builder::heading.related_pages'),
            ]);
        });

        return $block;
    }

    public function createArticleBlockType(): Blueprint
    {
        return Blueprint::query()->firstOrCreate([
            'key' => 'article',
            'type' => LayoutTypeEnum::Block,
        ], [
            'name' => __('capell-blog::generic.article'),
            'group' => BlueprintGroupEnum::System->value,
            'admin' => [
                'type_configurator' => PageTypeConfigurator::getKey(),
                'configurator' => ArticleBlockConfigurator::getKey(),
                'icon' => 'heroicon-o-newspaper',
            ],
            'meta' => [
                'component' => BlogBlockComponentEnum::Article,
                'margin' => ['xl'],
            ],
        ]);
    }

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

        $page = Page::query()->firstOrNew([
            'layout_id' => $layout->id,
            'site_id' => $site->id,
            'blueprint_id' => $type->id,
        ]);

        $page->mergeMeta([
            ...$meta,
            'component' => LivewirePageComponentEnum::BlogPage->value,
            'rendering_strategy' => RenderingStrategyEnum::FullLivewire->value,
        ]);

        $page->forceFill([
            'name' => __('capell-blog::generic.blog'),
        ]);

        $page->save();

        $languages->each(function (Language $language) use ($page): void {
            $page->translations()->firstOrCreate([
                'language_id' => $language->id,
            ], [
                'title' => __('capell-blog::generic.latest_articles'),
                'meta' => [
                    'label' => __('capell-blog::generic.blog'),
                    'no_results' => __('capell-blog::messages.no_articles_found'),
                    'slug' => 'blog',
                ],
            ]);
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
                'type_configurator' => PageTypeConfigurator::getKey(),
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

    public function createLatestArticlesBlock(?Collection $languages = null): Block
    {
        if (! $languages instanceof Collection) {
            $languages = Language::all();
        }

        $typeCreator = resolve(LayoutTypeCreator::class);
        $type = $typeCreator->resultsBlockType();

        $block = Block::query()->firstOrCreate([
            'key' => 'latest-articles',
        ], [
            'name' => __('capell-blog::generic.latest_articles'),
            'blueprint_id' => $type->id,
            'meta' => [
                'component' => LayoutBlockComponentEnum::PageLatest,
                'livewire' => false,
                'limit' => 5,
                'page_model' => Relation::getMorphAlias(Article::class),
                'page_group' => strtolower(ResourceEnum::Article->name),
                'pagination' => false,
                'with_date' => true,
                'with_image' => false,
                'with_summary' => true,
                'with_link_text' => true,
                'margin' => ['b-lg'],
            ],
            'admin' => [
                'icon' => 'heroicon-o-newspaper',
            ],
        ]);

        $block->forceFill([
            'blueprint_id' => $type->id,
            'component' => LayoutBlockComponentEnum::PageLatest->value,
            'is_livewire' => false,
            'meta' => [
                ...($block->meta ?? []),
                'component' => LayoutBlockComponentEnum::PageLatest->value,
                'livewire' => false,
                'limit' => 5,
                'page_model' => Relation::getMorphAlias(Article::class),
                'page_group' => strtolower(ResourceEnum::Article->name),
                'pagination' => false,
                'with_date' => true,
                'with_image' => false,
                'with_summary' => true,
                'with_link_text' => true,
                'margin' => ['b-lg'],
            ],
        ])->save();

        $languages->each(function (Language $language) use ($block): void {
            $block->translations()->firstOrCreate([
                'language_id' => $language->id,
            ], [
                'title' => __('capell-blog::generic.latest_articles'),
            ]);
        });

        return $block;
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
     * @param  array<string, array<string, mixed>>|null  $currentContainers
     * @param  array<string, array<string, mixed>>  $defaultContainers
     * @return array<string, array<string, mixed>>
     */
    private function withArticleLatestArticlesContainer(?array $currentContainers, array $defaultContainers): array
    {
        $containers = $currentContainers !== null && $currentContainers !== []
            ? $currentContainers
            : $defaultContainers;

        if (isset($containers['sidebar']['blocks']) && is_array($containers['sidebar']['blocks'])) {
            $containers['sidebar']['blocks'] = collect($containers['sidebar']['blocks'])
                ->reject(fn (array $block): bool => ($block['block_key'] ?? null) === 'latest-articles')
                ->values()
                ->all();
        }

        $containers['latest'] = $defaultContainers['latest'];

        return $containers;
    }

    private function blockKeys(array $containers): array
    {
        return collect($containers)
            ->flatMap(fn (array $container): array => $container['blocks'] ?? [])
            ->unique('block_key')
            ->pluck('block_key')
            ->values()
            ->all();
    }
}
