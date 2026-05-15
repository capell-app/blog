<?php

declare(strict_types=1);

namespace Capell\Blog\Support\Creator;

use Capell\Admin\Filament\Configurators\Pages\ResultsPageConfigurator;
use Capell\Admin\Filament\Configurators\Types\PageTypeConfigurator;
use Capell\Blog\Actions\EnsureArticlePublishingDefaultsAction;
use Capell\Blog\Actions\EnsureBlogPublishingSurfaceAction;
use Capell\Blog\Enums\BlogLayoutEnum;
use Capell\Blog\Enums\BlogPageTypeEnum;
use Capell\Blog\Enums\BlogTypeGroupEnum;
use Capell\Blog\Enums\ElementComponentEnum as BlogElementComponentEnum;
use Capell\Blog\Enums\ElementConfiguratorEnum;
use Capell\Blog\Enums\LivewirePageComponentEnum;
use Capell\Blog\Enums\ResourceEnum;
use Capell\Blog\Filament\Configurators\Articles\ArticlePageConfigurator;
use Capell\Blog\Filament\Configurators\Elements\ArticleElementConfigurator;
use Capell\Blog\Models\Article;
use Capell\Core\Actions\SetupPageUrlsAction;
use Capell\Core\Enums\BlueprintGroupEnum;
use Capell\Core\Enums\BlueprintSubjectEnum;
use Capell\Core\Enums\ContainerWidthEnum;
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
use Capell\LayoutBuilder\Enums\LivewireComponentsEnum;
use Capell\LayoutBuilder\Filament\Configurators\Types\ElementTypeConfigurator;
use Capell\LayoutBuilder\Models\Element;
use Capell\LayoutBuilder\Support\Creator\ElementCreator;
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
    public function setup(Site $site, bool $createElements = true): void
    {
        EnsureArticlePublishingDefaultsAction::run($createElements);
        EnsureBlogPublishingSurfaceAction::run($site, $site->getAllLanguages(), $createElements);
    }

    public function createTagPageType(): Blueprint
    {
        /** @var class-string<Blueprint> $typeMode */
        $typeMode = Blueprint::class;

        return $typeMode::query()->firstOrCreate([
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
                'with_image' => true,
                'with_summary' => true,
            ],
        ]);
    }

    public function createTagPage(Site $site, ?Page $parent = null, ?Collection $languages = null, ?Blueprint $type = null, ?Layout $layout = null): Page
    {
        $site->unsetRelation('siteDomains');
        $site->loadMissing(['language', 'siteDomains.language']);

        $type ??= $this->createTagPageType();
        $layout ??= $this->getResultsLayout();
        $languages ??= $site->getAllLanguages();
        $parent ??= $this->createTagsPage($site, $this->createBlogPage($site));

        $pageModel = Page::class;

        $page = $pageModel::query()->firstOrNew([
            'layout_id' => $layout->id,
            'site_id' => $site->id,
            'blueprint_id' => $type->id,
            'parent_id' => $parent?->getKey(),
        ], [
            'name' => __('capell-blog::generic.tag_page'),
        ]);

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

    public function createTagsPage(Site $site, ?Page $parent, ?Collection $languages = null, ?Blueprint $type = null, ?Layout $layout = null, bool $createElements = false): Page
    {
        $site->unsetRelation('siteDomains');
        $site->loadMissing(['language', 'siteDomains.language']);

        $type ??= $this->getPageType(PageTypeEnum::System);
        $layout ??= self::createTagsLayout();
        $languages ??= $site->getAllLanguages();

        if ($createElements) {
            $this->createTagsElement($languages);
            $resultsElementType = resolve(LayoutTypeCreator::class)->resultsElementType();
            resolve(ElementCreator::class)->latestPagesElement($resultsElementType, $languages);
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
        return Blueprint::query()->firstOrCreate([
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
                'component' => LivewirePageComponentEnum::ArchivePage,
                'livewire' => true,
                'hidden_from_selection' => true,
                'limit' => 10,
                'listable' => false,
                'pagination' => true,
                'rendering_strategy' => RenderingStrategyEnum::FullLivewire->value,
                'url_params' => ['date' => UrlParamTypeEnum::String->value],
                'with_date' => true,
                'with_image' => true,
                'with_summary' => true,
            ],
        ]);
    }

    public function createArchivesLayout(): Layout
    {
        $containers = [
            'main' => [
                'meta' => [
                    'colspan' => 9,
                ],
                'elements' => [
                    ['element_key' => 'breadcrumbs'],
                    ['element_key' => 'archives', 'meta' => ['show_page_content' => true, 'show_page_title' => true]],
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
                'elements' => [
                    ['element_key' => 'latest-articles', 'meta' => ['hide_no_results' => true]],
                    ['element_key' => 'tags', 'meta' => ['hide_no_results' => true]],
                ],
            ],
        ];

        return Layout::query()->firstOrCreate(['key' => BlogLayoutEnum::Archives->value], [
            'name' => __('capell-blog::generic.archives'),
            'group' => LayoutGroupEnum::System->value,
            'containers' => $containers,
            'elements' => $this->elementKeys($containers),
        ]);
    }

    public function createBlogPageLayout(): Layout
    {
        $containers = [
            'main' => [
                'meta' => [
                    'colspan' => 9,
                ],
                'elements' => [
                    ['element_key' => 'breadcrumbs'],
                    ['element_key' => 'page-content'],
                    ['element_key' => 'page-slot'],
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
                'elements' => [
                    ['element_key' => 'tags', 'meta' => ['hide_no_results' => true]],
                    ['element_key' => 'archives', 'meta' => ['hide_no_results' => true]],
                ],
            ],
        ];

        return Layout::query()->firstOrCreate(['key' => BlogLayoutEnum::BlogPage->value], [
            'name' => __('capell-blog::generic.blog_page'),
            'group' => LayoutGroupEnum::System->value,
            'containers' => $containers,
            'elements' => $this->elementKeys($containers),
        ]);
    }

    public function createTagsLayout(): Layout
    {
        $containers = [
            'main' => [
                'meta' => [
                    'colspan' => 9,
                ],
                'elements' => [
                    ['element_key' => 'breadcrumbs'],
                    ['element_key' => 'tags', 'meta' => ['show_page_title' => true, 'show_page_content' => true]],
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
                'elements' => [
                    ['element_key' => 'latest-pages', 'meta' => ['hide_no_results' => true]],
                ],
            ],
        ];

        return Layout::query()->firstOrCreate(['key' => BlogLayoutEnum::Tags->value], [
            'name' => __('capell-blog::generic.tags'),
            'group' => LayoutGroupEnum::System->value,
            'containers' => $containers,
            'elements' => $this->elementKeys($containers),
        ]);
    }

    public function createArchivesElement(?Collection $languages = null): Element
    {
        if (! $languages instanceof Collection) {
            $languages = Language::all();
        }

        $typeCreator = resolve(LayoutTypeCreator::class);
        $type = $typeCreator->resultsElementType();

        $element = Element::query()->firstOrCreate([
            'key' => 'archives',
        ], [
            'name' => __('capell-blog::generic.article_archives'),
            'blueprint_id' => $type->id,
            'meta' => [
                'component' => BlogElementComponentEnum::Archives,
                'page_group' => strtolower(ResourceEnum::Article->name),
                'pagination' => true,
                'with_image' => true,
                'with_date' => true,
                'with_link_text' => true,
                'with_summary' => true,
                'margin' => ['b-lg'],
            ],
        ]);

        $languages->each(function (Language $language) use ($element): void {
            $element->translations()->firstOrCreate([
                'language_id' => $language->id,
            ], [
                'title' => __('capell-blog::generic.archives'),
                'meta' => [
                    'no_results' => __('capell-blog::messages.no_archives_found'),
                ],
            ]);
        });

        return $element;
    }

    public function createTagsElement(Collection $languages): void
    {
        $elementModel = Element::class;

        $typeCreator = resolve(LayoutTypeCreator::class);
        $type = $typeCreator->resultsElementType();

        $element = $elementModel::query()->firstOrCreate([
            'key' => 'tags',
        ], [
            'name' => __('capell-blog::generic.tags'),
            'blueprint_id' => $type->id,
            'meta' => [
                'component' => BlogElementComponentEnum::Tags,
                'page_model' => Relation::getMorphAlias(Article::class),
                'size' => 'sm',
            ],
            'admin' => [
                'icon' => 'heroicon-' . Heroicon::OutlinedTag->value,
            ],
        ]);

        $languages->each(function (Language $language) use ($element): void {
            $element->translations()->firstOrCreate([
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

    public function createArticleLayout(bool $createElements = true): Layout
    {
        if ($createElements) {
            $languages = Language::all();
            $elementCreator = resolve(ElementCreator::class);
            $typeCreator = resolve(LayoutTypeCreator::class);
            $systemElementType = $typeCreator->systemElementType();
            $pageContentElementType = $typeCreator->pageContentElementType();
            $resultsType = $typeCreator->resultsElementType();

            $elementCreator->breadcrumbElement($systemElementType);
            $elementCreator->pageSlotElement($systemElementType);
            $elementCreator->pageContentElement($pageContentElementType);

            $articleType = $this->createArticleElementType();
            $this->createArticleElement($articleType);

            $this->relatedArticlesElement($resultsType, $languages);
            $this->createTagsElement($languages);
            $this->createArchivesElement($languages);
        }

        $containers = [
            'main' => [
                'meta' => [
                    'colspan' => 9,
                ],
                'elements' => [
                    ['element_key' => 'breadcrumbs'],
                    ['element_key' => 'article'],
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
                'elements' => [
                    ['element_key' => 'latest-articles', 'meta' => ['hide_no_results' => true]],
                    ['element_key' => 'tags', 'meta' => ['hide_no_results' => true]],
                    ['element_key' => 'archives', 'meta' => ['hide_no_results' => true]],
                ],
            ],
        ];

        return Layout::query()->firstOrCreate(['key' => BlogLayoutEnum::Article->value], [
            'name' => __('capell-blog::generic.article'),
            'group' => LayoutGroupEnum::Default->value,
            'containers' => $containers,
            'elements' => $this->elementKeys($containers),
        ]);
    }

    public function createArticlePageType(): Blueprint
    {
        return Blueprint::query()->firstOrCreate([
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
        ]);
    }

    public function createArticleElement(Blueprint $type): Element
    {
        return Element::query()->firstOrCreate([
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
    }

    public function relatedArticlesElement(?Blueprint $type = null, ?Collection $languages = null): Element
    {
        if (! $type instanceof Blueprint) {
            $typeCreator = resolve(LayoutTypeCreator::class);
            $type = $typeCreator->resultsElementType();
        }

        if (! $languages instanceof Collection) {
            $languages = Language::all();
        }

        $element = Element::query()->firstOrCreate([
            'key' => 'related-pages',
        ], [
            'name' => __('capell-admin::generic.related_pages'),
            'blueprint_id' => $type->id,
            'meta' => [
                'component' => BlogElementComponentEnum::PageRelated,
                'limit' => 6,
                'pagination' => false,
                'page_model' => Relation::getMorphAlias(Article::class),
                'exclude_types' => ['home'],
                'exclude_parent' => true,
                'with_summary' => true,
                'with_link_text' => true,
                'with_image' => true,
                'columns' => 1,
            ],
            'admin' => [
                'icon' => 'heroicon-c-link',
                'type_configurator' => ElementTypeConfigurator::getKey(),
                'configurator' => ElementConfiguratorEnum::Related->name,
            ],
        ]);

        $languages->each(function (Language $language) use ($element): void {
            $element->translations()->firstOrCreate([
                'language_id' => $language->id,
            ], [
                'title' => __('capell-layout-builder::heading.related_pages'),
            ]);
        });

        return $element;
    }

    public function createArticleElementType(): Blueprint
    {
        return Blueprint::query()->firstOrCreate([
            'key' => 'article',
            'type' => LayoutTypeEnum::Element,
        ], [
            'name' => __('capell-blog::generic.article'),
            'group' => BlueprintGroupEnum::System->value,
            'admin' => [
                'type_configurator' => PageTypeConfigurator::getKey(),
                'configurator' => ArticleElementConfigurator::getKey(),
                'icon' => 'heroicon-o-newspaper',
            ],
            'meta' => [
                'component' => BlogElementComponentEnum::Article,
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

        $page->mergeMeta($meta);

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
        return Blueprint::query()->firstOrCreate([
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
                'with_image' => true,
                'with_summary' => true,
            ],
        ]);
    }

    public function createLatestArticlesElement(?Collection $languages = null): Element
    {
        if (! $languages instanceof Collection) {
            $languages = Language::all();
        }

        $typeCreator = resolve(LayoutTypeCreator::class);
        $type = $typeCreator->resultsElementType();

        $element = Element::query()->firstOrCreate([
            'key' => 'latest-articles',
        ], [
            'name' => __('capell-blog::generic.latest_articles'),
            'blueprint_id' => $type->id,
            'meta' => [
                'component' => LivewireComponentsEnum::PagesElement,
                'livewire' => true,
                'limit' => 5,
                'page_model' => Relation::getMorphAlias(Article::class),
                'page_group' => strtolower(ResourceEnum::Article->name),
                'pagination' => false,
                'with_date' => true,
                'with_image' => true,
                'with_summary' => true,
                'with_link_text' => true,
                'margin' => ['b-lg'],
            ],
            'admin' => [
                'icon' => 'heroicon-o-newspaper',
            ],
        ]);

        $languages->each(function (Language $language) use ($element): void {
            $element->translations()->firstOrCreate([
                'language_id' => $language->id,
            ], [
                'title' => __('capell-blog::generic.latest_articles'),
            ]);
        });

        return $element;
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

    private function getLayout(LayoutEnum|string $key): Layout
    {
        if ($key instanceof LayoutEnum) {
            $key = $key->value;
        }

        $layoutModel = Layout::class;

        $layout = $layoutModel::query()->firstWhere('key', $key);

        if ($layout !== null) {
            return $layout;
        }

        return resolve(LayoutCreator::class)->create($key);
    }

    private function getResultsLayout(): Layout
    {
        $layout = $this->getLayout(LayoutEnum::Results);
        $containers = $layout->getAttribute('containers');

        if (is_array($containers) && $containers !== []) {
            return $layout;
        }

        $elementCreator = resolve(ElementCreator::class);
        $elementCreator->breadcrumbElement();
        $elementCreator->pageContentElement();
        $elementCreator->pageSlotElement();
        $elementCreator->latestPagesElement();

        $containers = [
            'main' => [
                'meta' => [
                    'colspan' => 9,
                ],
                'elements' => [
                    ['element_key' => 'breadcrumbs'],
                    ['element_key' => 'page-content'],
                    ['element_key' => 'page-slot'],
                ],
            ],
            'sidebar' => [
                'meta' => [
                    'colspan' => 3,
                    'override_columns' => 1,
                    'container' => ContainerWidthEnum::Full,
                    'padding' => ['md'],
                    'html_class' => 'sidebar-sticky space-y-8',
                ],
                'elements' => [
                    ['element_key' => 'latest-pages'],
                ],
            ],
        ];

        $layout->update([
            'containers' => $containers,
            'elements' => $this->elementKeys($containers),
        ]);

        return $layout;
    }

    private function elementKeys(array $containers): array
    {
        return collect($containers)
            ->flatMap(fn (array $container): array => $container['elements'] ?? [])
            ->unique('element_key')
            ->pluck('element_key')
            ->values()
            ->all();
    }
}
