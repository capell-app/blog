<?php

declare(strict_types=1);

namespace Capell\Blog\Providers;

use Capell\Admin\Data\AdminSurfaceContributionData;
use Capell\Admin\Enums\ResourceEnum as AdminResourceEnum;
use Capell\Admin\Facades\CapellAdmin;
use Capell\Blog\Actions\ClearBlogContentCacheAction;
use Capell\Blog\Actions\ClearBlogTagCacheAction;
use Capell\Blog\Actions\SanitizeBlogHtmlAction;
use Capell\Blog\Enums\LivewirePageComponentEnum;
use Capell\Blog\Enums\ResourceEnum;
use Capell\Blog\Enums\WidgetComponentEnum;
use Capell\Blog\Http\Controllers\BlogFeedController;
use Capell\Blog\Listeners\ArticleTranslationSavedListener;
use Capell\Blog\Models\Article;
use Capell\Blog\Policies\ArticlePolicy;
use Capell\Blog\Support\BlogFrontendRuntimeManifestContributor;
use Capell\Blog\Support\BlogModelRegistrar;
use Capell\Blog\Support\BlogSidebarWidgetContributor;
use Capell\Blog\Support\EditorialCalendar\BlogEditorialCalendarEventContributor;
use Capell\Blog\Support\PublicUrls\BlogPublicUrlContributor;
use Capell\ContentSections\Models\Section;
use Capell\Core\Actions\RegisterBlazeOptimizedViewsAction;
use Capell\Core\Data\PageTypeData;
use Capell\Core\Data\RenderableDefinitionData;
use Capell\Core\Data\VendorAssetData;
use Capell\Core\Enums\RenderableTypeEnum;
use Capell\Core\Facades\CapellCore;
use Capell\Core\Models\Page;
use Capell\Core\Models\Site;
use Capell\Core\Models\Translation;
use Capell\Core\Support\Packages\AbstractPackageServiceProvider;
use Capell\Core\Support\Renderables\RenderableRegistry;
use Capell\Frontend\Contracts\FrontendRuntimeManifestContributor;
use Capell\Frontend\Support\Cache\CacheInvalidationRegistry;
use Capell\LayoutBuilder\Contracts\LayoutSidebarWidgetContributor;
use Capell\PublishingStudio\Contracts\EditorialCalendarEventContributor;
use Capell\PublishingStudio\WorkspaceRegistry;
use Capell\SiteDiscovery\Contracts\PublicUrlContributor;
use Capell\Tags\Models\Tag;
use Capell\Tags\Support\TagModelRegistrar;
use Composer\InstalledVersions;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphToMany;
use Illuminate\Foundation\Console\AboutCommand;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Route;
use Livewire\Livewire;
use Override;
use Spatie\LaravelPackageTools\Package;
use Spatie\MediaLibrary\MediaCollections\Events\MediaHasBeenAddedEvent;

final class BlogServiceProvider extends AbstractPackageServiceProvider
{
    private const string LAYOUT_SIDEBAR_ELEMENT_CONTRIBUTOR = LayoutSidebarWidgetContributor::class;

    private const string EDITORIAL_CALENDAR_EVENT_CONTRIBUTOR = EditorialCalendarEventContributor::class;

    private const string PUBLIC_URL_CONTRIBUTOR = PublicUrlContributor::class;

    private const string WORKSPACE_REGISTRY = WorkspaceRegistry::class;

    public static string $name = 'capell-blog';

    public static string $packageName = 'capell-app/blog';

    public function configurePackage(Package $package): void
    {
        $package
            ->name(self::$name)
            ->hasViews(self::$name)
            ->hasTranslations();
    }

    public function bootingPackage(): void
    {
        Route::get('/blog/feed.xml', [BlogFeedController::class, '__invoke'])
            ->defaults('format', 'xml')
            ->name('capell.blog.feed.xml');

        Route::get('/blog/feed.rss', [BlogFeedController::class, '__invoke'])
            ->defaults('format', 'rss')
            ->name('capell.blog.feed.rss');

        Route::get('/blog/feed.atom', [BlogFeedController::class, '__invoke'])
            ->defaults('format', 'atom')
            ->name('capell.blog.feed.atom');
    }

    public function registeringPackage(): void
    {
        $this->app->register(AdminServiceProvider::class);
        $this->app->register(ConsoleServiceProvider::class);

        if (interface_exists(self::LAYOUT_SIDEBAR_ELEMENT_CONTRIBUTOR)) {
            $this->app->tag([BlogSidebarWidgetContributor::class], self::LAYOUT_SIDEBAR_ELEMENT_CONTRIBUTOR::TAG);
        }

        BlogModelRegistrar::register();
        $this->registerTypes();

        $this->app->booting(function (): void {
            if ($this->isPackageInstalled()) {
                $this->registerAdminResources();
            }
        });

        $this->app->booted(function (): void {
            if (! $this->isPackageInstalled()) {
                return;
            }

            $this->bootInstalledPackage();
        });
    }

    #[Override]
    protected function isPackageInstalled(): bool
    {
        return CapellCore::getPackage(self::$packageName)->isInstalled();
    }

    #[Override]
    protected function isLivewireV3(): bool
    {
        $version = InstalledVersions::getVersion('livewire/livewire');

        return is_string($version) && version_compare($version, '4.0.0', '<');
    }

    private function bootInstalledPackage(): self
    {
        return $this
            ->registerRelationships()
            ->registerModels()
            ->registerPolicies()
            ->registerModelRelations()
            ->registerAdminResources()
            ->registerAboutCommand()
            ->registerPackageAssets()
            ->registerBlazeComponents()
            ->registerSafeHtmlDirective()
            ->registerBladeComponents()
            ->registerPageRenderables()
            ->registerWidgetRenderables()
            ->registerLivewireComponents()
            ->registerTypes()
            ->registerPublicUrlContributors()
            ->registerEditorialCalendarContributors()
            ->registerFrontendRuntimeManifestContributors()
            ->registerCacheInvalidationDependencies()
            ->registerTranslationEvents()
            ->registerTagCacheEvents()
            ->registerArticleMediaCacheEvents()
            ->registerPublishingStudio();
    }

    private function registerPackageAssets(): self
    {
        CapellCore::registerVendorAsset(
            VendorAssetData::tailwindSource('resources/views/**/*.blade.php', self::$packageName),
        );

        return $this;
    }

    private function registerModels(): self
    {
        BlogModelRegistrar::register();

        return $this;
    }

    private function registerPolicies(): self
    {
        Gate::policy(Article::class, ArticlePolicy::class);

        return $this;
    }

    private function registerAdminResources(): self
    {
        CapellAdmin::contributeToAdminSurface(AdminSurfaceContributionData::resource(
            class: ResourceEnum::Article->value,
            group: AdminResourceEnum::Page->name,
            name: strtolower(ResourceEnum::Article->name),
        ));

        CapellAdmin::contributeToAdminSurface(AdminSurfaceContributionData::resource(
            class: ResourceEnum::Tag->value,
            group: ResourceEnum::Tag->name,
        ));

        return $this;
    }

    private function registerModelRelations(): self
    {
        TagModelRegistrar::registerTaggable(Article::class, inverseRelation: 'articles');
        TagModelRegistrar::registerTaggable(Page::class);
        TagModelRegistrar::registerTaggable(Section::class);

        return $this;
    }

    private function registerBladeComponents(): self
    {
        Blade::componentNamespace('Capell\\Blog\\View\\Components', 'capell-blog');
        Blade::anonymousComponentNamespace('Capell\\Blog\\View\\Components');

        return $this;
    }

    private function registerSafeHtmlDirective(): self
    {
        Blade::directive(
            'safeBlogHtml',
            static fn (string $expression): string => sprintf('<?php echo \\%s::run(%s); ?>', SanitizeBlogHtmlAction::class, $expression),
        );

        return $this;
    }

    private function registerBlazeComponents(): self
    {
        RegisterBlazeOptimizedViewsAction::run(__DIR__ . '/../../resources/views/components');

        return $this;
    }

    private function registerLivewireComponents(): self
    {
        if ($this->isLivewireV3()) {
            foreach (LivewirePageComponentEnum::getComponents() as $name => $component) {
                if (! $component) {
                    continue;
                }

                Livewire::component($name, $component);
            }
        } else {
            Livewire::addNamespace(
                namespace: 'capell-blog',
                classNamespace: 'Capell\\Blog\\Livewire',
                classPath: __DIR__ . '/../Livewire',
                classViewPath: __DIR__ . '/../../resources/views/livewire',
            );
        }

        return $this;
    }

    private function registerPageRenderables(): self
    {
        $registry = resolve(RenderableRegistry::class);

        foreach (LivewirePageComponentEnum::cases() as $pageComponent) {
            $livewireComponent = $pageComponent->getComponent();
            if ($livewireComponent === null) {
                continue;
            }

            if ($livewireComponent === '') {
                continue;
            }

            $registry->register(new RenderableDefinitionData(
                key: $pageComponent->value,
                type: RenderableTypeEnum::Page,
                livewire: $pageComponent->value,
            ));
        }

        return $this;
    }

    private function registerWidgetRenderables(): self
    {
        $registry = resolve(RenderableRegistry::class);

        foreach (WidgetComponentEnum::cases() as $widgetComponent) {
            $registry->register(new RenderableDefinitionData(
                key: $widgetComponent->value,
                type: 'layout-widget',
                blade: $widgetComponent->value,
            ));
        }

        return $this;
    }

    private function registerAboutCommand(): self
    {
        if ($this->app->runningInConsole() && (class_exists(AboutCommand::class) && class_exists(InstalledVersions::class))) {
            AboutCommand::add('Capell', [
                self::$name => fn () => InstalledVersions::getPrettyVersion('capell-app/blog'),
            ]);
        }

        return $this;
    }

    private function registerRelationships(): self
    {
        Site::resolveRelationUsing(
            'tags',
            fn (Site $model): HasMany => $model->hasMany(Tag::class, 'site_id'),
        );

        if (class_exists(Section::class)) {
            Tag::resolveRelationUsing(
                'sections',
                fn (Tag $model): MorphToMany => $model->morphedByMany(Section::class, 'taggable', 'taggables'),
            );
        }

        return $this;
    }

    private function registerPublicUrlContributors(): self
    {
        $publicUrlContributorContract = self::PUBLIC_URL_CONTRIBUTOR;

        if (interface_exists($publicUrlContributorContract)) {
            $this->app->singleton(BlogPublicUrlContributor::class);
            $this->app->tag([BlogPublicUrlContributor::class], $publicUrlContributorContract::TAG);
        }

        return $this;
    }

    private function registerFrontendRuntimeManifestContributors(): self
    {
        if (interface_exists(FrontendRuntimeManifestContributor::class)) {
            $this->app->tag([BlogFrontendRuntimeManifestContributor::class], FrontendRuntimeManifestContributor::TAG);
        }

        return $this;
    }

    private function registerEditorialCalendarContributors(): self
    {
        $editorialCalendarContributorContract = self::EDITORIAL_CALENDAR_EVENT_CONTRIBUTOR;

        if (interface_exists($editorialCalendarContributorContract)) {
            $contributorClass = BlogEditorialCalendarEventContributor::class;

            $this->app->singleton($contributorClass);
            $this->app->tag([$contributorClass], $editorialCalendarContributorContract::TAG);
        }

        return $this;
    }

    private function registerTranslationEvents(): self
    {
        Event::listen('eloquent.saved: ' . Translation::class, ArticleTranslationSavedListener::class);

        return $this;
    }

    private function registerCacheInvalidationDependencies(): self
    {
        $cacheInvalidationRegistryClass = CacheInvalidationRegistry::class;

        if (! class_exists($cacheInvalidationRegistryClass) || ! $this->app->bound($cacheInvalidationRegistryClass)) {
            return $this;
        }

        $registry = resolve($cacheInvalidationRegistryClass);

        if (! is_object($registry) || ! method_exists($registry, 'registerDependency')) {
            return $this;
        }

        $registry->registerDependency(Article::class, [
            'page-tags-*',
            'site-*-blog-page',
            'site-*-archive-page',
            'site-*-tag-page',
            'site-tags-*',
        ]);

        $registry->registerDependency(Tag::class, [
            'page-tags-*',
            'site-*-tag-page',
            'site-tags-*',
        ]);

        return $this;
    }

    private function registerTagCacheEvents(): self
    {
        Tag::created(function (Tag $tag): void {
            ClearBlogTagCacheAction::run($tag);
        });
        Tag::updating(function (Tag $tag): void {
            ClearBlogTagCacheAction::run($tag);
        });
        Tag::deleting(function (Tag $tag): void {
            ClearBlogTagCacheAction::run($tag);
        });

        return $this;
    }

    private function registerArticleMediaCacheEvents(): self
    {
        Event::listen(MediaHasBeenAddedEvent::class, function (MediaHasBeenAddedEvent $event): void {
            $model = $event->media->model;

            if (! $model instanceof Article) {
                return;
            }

            ClearBlogContentCacheAction::run($model);
        });

        return $this;
    }

    private function registerTypes(): self
    {
        $this->surface()->pageType(
            new PageTypeData(
                name: 'article',
                model: Article::class,
                label: fn (): string => __('capell-blog::generic.article'),
            ),
        );

        return $this;
    }

    private function registerPublishingStudio(): self
    {
        $workspaceRegistryClass = self::WORKSPACE_REGISTRY;

        if (class_exists($workspaceRegistryClass)) {
            $workspaceRegistryClass::register(Article::class);
        }

        return $this;
    }
}
