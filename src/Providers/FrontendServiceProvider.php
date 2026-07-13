<?php

declare(strict_types=1);

namespace Capell\Blog\Providers;

use Capell\Blog\Enums\BlogPageTypeEnum;
use Capell\Blog\Enums\BlogTypeGroupEnum;
use Capell\Blog\Enums\LivewirePageComponentEnum;
use Capell\Blog\Support\Loader\BlogLoader;
use Capell\Blog\Support\Loader\TagLoader;
use Capell\Blog\Support\RenderHooks\AfterTitleRenderHook;
use Capell\Blog\Support\RenderHooks\ArticleMetaRenderHook;
use Capell\Blog\Support\RenderHooks\BeforeContentTagsRenderHook;
use Capell\Blog\Support\RenderHooks\FooterPagesRenderHook;
use Capell\Blog\Support\RenderHooks\FooterTagsRenderHook;
use Capell\Blog\Support\Sitemap\ArchivesSitemap;
use Capell\Blog\Support\Sitemap\ArticlesSitemap;
use Capell\Blog\Support\Sitemap\TagsSitemap;
use Capell\Blog\Support\StaticSite\BlogStaticSiteExtension;
use Capell\Core\Contracts\Pageable;
use Capell\Core\Data\RenderableDefinitionData;
use Capell\Core\Enums\RenderableTypeEnum;
use Capell\Core\Facades\CapellCore;
use Capell\Core\Models\Language;
use Capell\Core\Models\Site;
use Capell\Core\Support\Renderables\RenderableRegistry;
use Capell\Frontend\Enums\RenderHookLocation;
use Capell\Frontend\Events\FrontendContextResolved;
use Capell\Frontend\Support\Render\FrontendHookRegistrar;
use Capell\Frontend\Support\State\FrontendState;
use Capell\HtmlCache\Support\StaticSite\StaticSiteExtensionRegistry;
use Capell\SiteDiscovery\Support\Sitemap\SitemapPageRegistry;
use Capell\Tags\Models\Tag;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\ServiceProvider;

final class FrontendServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        $this->app->booted(function (): void {
            if (! CapellCore::getPackage('capell-app/blog')->isInstalled()) {
                return;
            }

            $this->registerSitemapPages();
            $this->registerPageRenderables();
            $this->registerRenderHooks();
            $this->registerArchiveValidation();
            $this->registerTagVariables();
            $this->registerStaticSiteExtensions();
        });
    }

    private function registerPageRenderables(): void
    {
        $registry = resolve(RenderableRegistry::class);

        foreach (LivewirePageComponentEnum::cases() as $pageComponent) {
            if ($pageComponent->getComponent() === null) {
                continue;
            }

            $registry->register(new RenderableDefinitionData(
                key: $pageComponent->value,
                type: RenderableTypeEnum::Page,
                livewire: $pageComponent->value,
            ));
        }
    }

    private function registerTagVariables(): void
    {
        Event::listen(FrontendContextResolved::class, function (FrontendContextResolved $event): void {
            $context = $event->context;
            $page = $context->page();

            if (! $page instanceof Pageable || $page->blueprint?->key !== BlogPageTypeEnum::Tag->value) {
                return;
            }

            $tagSlug = $context->params['tag'] ?? null;

            if (
                ! is_string($tagSlug)
                || $tagSlug === ''
                || ! $context->site instanceof Site
                || ! $context->language instanceof Language
            ) {
                return;
            }

            $tag = TagLoader::tagPage($tagSlug, $context->site, $context->language);

            if (! $tag instanceof Tag) {
                return;
            }

            $tagName = $tag->getTranslation('name', $context->language->code);
            $context->params['Tag_name'] = $tagName;
            $context->params['tag_name'] = $tagName;

            resolve(FrontendState::class)->withParams($context->params);
        });
    }

    private function registerArchiveValidation(): void
    {
        Event::listen(FrontendContextResolved::class, function (FrontendContextResolved $event): void {
            $context = $event->context;
            $page = $context->page();

            if (! $page instanceof Pageable || $page->blueprint?->key !== BlogPageTypeEnum::Archive->value) {
                return;
            }

            $date = $context->params['date'] ?? null;

            abort_if(! is_string($date) || ! preg_match('/^(?<year>\d{4})-(?<month>\d{2})$/', $date, $matches), 404);

            $year = (int) $matches['year'];
            $month = (int) $matches['month'];
            $site = $context->site;
            $language = $context->language;

            abort_if($month < 1 || $month > 12, 404);

            if (! $site instanceof Site || ! $language instanceof Language) {
                return;
            }

            $archives = BlogLoader::getArchives(
                site: $site,
                language: $language,
                group: $page->blueprint->meta['page_group'] ?? BlogTypeGroupEnum::Article->value,
                pagination: false,
            );

            $exists = $archives->contains(
                fn (mixed $archive): bool => $archive->year === $year && $archive->month === $month,
            );

            abort_if(! $exists, 404);
        });
    }

    private function registerSitemapPages(): void
    {
        if (! class_exists(SitemapPageRegistry::class)) {
            return;
        }

        $registry = resolve(SitemapPageRegistry::class);
        $registry->register('archives', ArchivesSitemap::class);
        $registry->register('articles', ArticlesSitemap::class);
        $registry->register('tags', TagsSitemap::class);
    }

    private function registerRenderHooks(): void
    {
        $registrar = resolve(FrontendHookRegistrar::class);

        $registrar->contribute(
            location: RenderHookLocation::Footer,
            extension: new FooterTagsRenderHook,
            owner: 'capell-app/blog',
            key: 'footer-tags',
            target: 'footer.index',
            cacheSafe: true,
        );

        $registrar->contribute(
            location: RenderHookLocation::Footer,
            extension: new FooterPagesRenderHook,
            owner: 'capell-app/blog',
            key: 'footer-pages',
            target: 'footer.index',
            cacheSafe: true,
        );

        $registrar->contribute(
            location: RenderHookLocation::ArticleMeta,
            extension: new ArticleMetaRenderHook,
            owner: 'capell-app/blog',
            key: 'article-meta',
            cacheSafe: false,
        );

        $registrar->contribute(
            location: RenderHookLocation::BeforeContent,
            extension: new BeforeContentTagsRenderHook,
            owner: 'capell-app/blog',
            key: 'before-content-tags',
            cacheSafe: true,
        );

        $registrar->contribute(
            location: RenderHookLocation::AfterTitle,
            extension: new AfterTitleRenderHook,
            owner: 'capell-app/blog',
            key: 'after-title',
            cacheSafe: false,
        );
    }

    private function registerStaticSiteExtensions(): void
    {
        if (
            ! CapellCore::isPackageInstalled('capell-app/html-cache')
            || ! app()->bound(StaticSiteExtensionRegistry::class)
        ) {
            return;
        }

        $registry = resolve(StaticSiteExtensionRegistry::class);

        if (! $registry->has('blog-tags-archives')) {
            $registry->register('blog-tags-archives', resolve(BlogStaticSiteExtension::class));
        }
    }
}
