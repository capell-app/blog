<?php

declare(strict_types=1);

namespace Capell\Blog\Providers;

use Capell\Admin\Data\AdminSurfaceContributionData;
use Capell\Admin\Enums\ResourceEnum as AdminResourceEnum;
use Capell\Admin\Facades\CapellAdmin;
use Capell\Blog\Actions\ClearBlogTagCacheAction;
use Capell\Blog\Enums\ElementComponentEnum;
use Capell\Blog\Enums\LivewirePageComponentEnum;
use Capell\Blog\Enums\ResourceEnum;
use Capell\Blog\Listeners\ArticleTranslationSavedListener;
use Capell\Blog\Models\Article;
use Capell\Blog\Support\BlogModelRegistrar;
use Capell\Blog\Support\BlogSidebarElementContributor;
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
use Capell\LayoutBuilder\Contracts\LayoutSidebarElementContributor;
use Capell\PublishingStudio\WorkspaceRegistry;
use Capell\Tags\Models\Tag;
use Composer\InstalledVersions;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphToMany;
use Illuminate\Foundation\Console\AboutCommand;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\Event;
use Livewire\Livewire;
use Spatie\LaravelPackageTools\Package;

class BlogServiceProvider extends AbstractPackageServiceProvider
{
    private const LAYOUT_SIDEBAR_ELEMENT_CONTRIBUTOR = LayoutSidebarElementContributor::class;

    public static string $name = 'capell-blog';

    public static string $packageName = 'capell-app/blog';

    public function configurePackage(Package $package): void
    {
        $package
            ->name(self::$name)
            ->hasViews(self::$name)
            ->hasTranslations();
    }

    public function registeringPackage(): void
    {
        $this->app->register(AdminServiceProvider::class);
        $this->app->register(ConsoleServiceProvider::class);

        if (interface_exists(self::LAYOUT_SIDEBAR_ELEMENT_CONTRIBUTOR)) {
            $this->app->tag([BlogSidebarElementContributor::class], self::LAYOUT_SIDEBAR_ELEMENT_CONTRIBUTOR::TAG);
        }

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

    private function isPackageInstalled(): bool
    {
        return CapellCore::getPackage(static::$packageName)->isInstalled();
    }

    private function bootInstalledPackage(): self
    {
        return $this
            ->registerRelationships()
            ->registerModels()
            ->registerModelRelations()
            ->registerAdminResources()
            ->registerAboutCommand()
            ->registerPackageAssets()
            ->registerBlazeComponents()
            ->registerBladeComponents()
            ->registerPageRenderables()
            ->registerElementRenderables()
            ->registerLivewireComponents()
            ->registerTypes()
            ->registerTranslationEvents()
            ->registerTagCacheEvents()
            ->registerPublishingStudio();
    }

    private function registerPackageAssets(): self
    {
        CapellCore::registerVendorAsset(
            VendorAssetData::tailwindSource('resources/views/**/*.blade.php', static::$packageName),
        );

        return $this;
    }

    private function registerModels(): self
    {
        BlogModelRegistrar::register();

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
        CapellCore::registerModelRelations(Page::class, 'tags');
        CapellCore::registerModelRelations(Section::class, 'tags');

        Tag::resolveRelationUsing(
            'articles',
            fn (Tag $tag): MorphToMany => $tag->morphedByMany(Article::class, 'taggable', 'taggables'),
        );

        return $this;
    }

    private function registerBladeComponents(): self
    {
        Blade::componentNamespace('Capell\\Blog\\View\\Components', 'capell-blog');
        Blade::anonymousComponentNamespace('Capell\\Blog\\View\\Components');

        return $this;
    }

    private function registerBlazeComponents(): self
    {
        foreach ([
            __DIR__ . '/../../resources/views/components/article-meta.blade.php',
            __DIR__ . '/../../resources/views/components/asset-after-title.blade.php',
            __DIR__ . '/../../resources/views/components/footer',
            __DIR__ . '/../../resources/views/components/page',
            __DIR__ . '/../../resources/views/components/tag.blade.php',
        ] as $path) {
            RegisterBlazeOptimizedViewsAction::run($path);
        }

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

    private function registerElementRenderables(): self
    {
        $registry = resolve(RenderableRegistry::class);

        foreach (ElementComponentEnum::cases() as $elementComponent) {
            $registry->register(new RenderableDefinitionData(
                key: $elementComponent->value,
                type: RenderableTypeEnum::Element,
                blade: $elementComponent->value,
            ));
        }

        return $this;
    }

    private function isLivewireV3(): bool
    {
        $version = InstalledVersions::getVersion('livewire/livewire');

        return version_compare($version, '4.0.0', '<');
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
        Page::resolveRelationUsing(
            'tags',
            fn (Page $model): MorphToMany => $model->morphToMany(
                Tag::class,
                'taggable',
                'taggables',
            ),
        );

        Site::resolveRelationUsing(
            'tags',
            fn (Site $model): HasMany => $model->hasMany(Tag::class, 'site_id'),
        );

        if (class_exists(Section::class)) {
            Section::resolveRelationUsing(
                'tags',
                fn (Section $model): MorphToMany => $model->morphToMany(Tag::class, 'taggable', 'taggables'),
            );

            Tag::resolveRelationUsing(
                'sections',
                fn (Tag $model): MorphToMany => $model->morphedByMany(Section::class, 'taggable', 'taggables'),
            );
        }

        return $this;
    }

    private function registerTranslationEvents(): self
    {
        Event::listen('eloquent.saved: ' . Translation::class, ArticleTranslationSavedListener::class);

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

    private function registerTypes(): self
    {
        CapellCore::registerPageType(
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
        WorkspaceRegistry::register(Article::class);

        return $this;
    }
}
