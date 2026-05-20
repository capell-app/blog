<?php

declare(strict_types=1);

namespace Capell\Blog\Providers;

use Capell\Blog\Enums\BlogPageTypeEnum;
use Capell\Blog\Enums\BlogTypeGroupEnum;
use Capell\Blog\Enums\LivewirePageComponentEnum;
use Capell\Blog\Support\Loader\BlogLoader;
use Capell\Blog\Support\Loader\TagLoader;
use Capell\Blog\Support\Sitemap\ArchivesSitemap;
use Capell\Blog\Support\Sitemap\ArticlesSitemap;
use Capell\Blog\Support\Sitemap\TagsSitemap;
use Capell\Blog\Support\StaticSite\BlogStaticSiteExtension;
use Capell\Blog\View\Components\ArticleMeta;
use Capell\Blog\View\Components\AssetAfterTitle;
use Capell\Blog\View\Components\Footer\Pages;
use Capell\Blog\View\Components\Footer\Tags;
use Capell\Blog\View\Components\Page\BeforeContentTags;
use Capell\Core\Contracts\Pageable;
use Capell\Core\Data\RenderableDefinitionData;
use Capell\Core\Enums\RenderableTypeEnum;
use Capell\Core\Facades\CapellCore;
use Capell\Core\Models\Language;
use Capell\Core\Models\Site;
use Capell\Core\Support\Renderables\RenderableRegistry;
use Capell\Frontend\Data\RenderHookContext;
use Capell\Frontend\Enums\RenderHookLocation;
use Capell\Frontend\Events\FrontendContextResolved;
use Capell\Frontend\Support\Render\RenderHookRegistry;
use Capell\Frontend\Support\State\FrontendState;
use Capell\HtmlCache\Support\StaticSite\StaticSiteExtensionRegistry;
use Capell\SiteDiscovery\Support\Sitemap\SitemapPageRegistry;
use Capell\Tags\Models\Tag;
use Illuminate\Contracts\View\View;
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

            if (! $page instanceof Pageable || $page->type?->key !== BlogPageTypeEnum::Tag->value) {
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

            if (! $page instanceof Pageable || $page->type?->key !== BlogPageTypeEnum::Archive->value) {
                return;
            }

            $date = $context->params['date'] ?? null;

            abort_if(! is_string($date) || ! preg_match('/^(?<year>\d{4})-(?<month>\d{2})$/', $date, $matches), 404);

            $year = (int) $matches['year'];
            $month = (int) $matches['month'];

            abort_if($month < 1 || $month > 12, 404);

            $archives = BlogLoader::getArchives(
                site: $context->site,
                language: $context->language,
                group: $page->type->meta['page_group'] ?? BlogTypeGroupEnum::Article->value,
                pagination: false,
            );

            $exists = $archives->contains(
                fn (mixed $archive): bool => (int) $archive->year === $year && (int) $archive->month === $month,
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
        resolve(RenderHookRegistry::class)->register(
            RenderHookLocation::Footer,
            function (RenderHookContext $context): ?View {
                $view = resolve(Tags::class, [
                    'item' => $context->item,
                ])->render();

                return $view instanceof View ? $view : null;
            },
            target: 'footer.index',
        );

        resolve(RenderHookRegistry::class)->register(
            RenderHookLocation::Footer,
            fn (RenderHookContext $context): ?View => resolve(Pages::class, [
                'item' => $context->item,
            ])
                ?->render(),
            target: 'footer.index',
        );

        resolve(RenderHookRegistry::class)->register(
            RenderHookLocation::ArticleMeta,
            function (RenderHookContext $context): string|View|null {
                $item = is_array($context->item) ? $context->item : [];

                return resolve(ArticleMeta::class, [
                    'item' => $context->item ?? null,
                    'withAuthor' => $item['withAuthor'] ?? false,
                    'author' => $item['author'] ?? null,
                    'articleMetaData' => $item['articleMetaData'] ?? null,
                ])
                    ?->render();
            },
        );

        resolve(RenderHookRegistry::class)->register(
            RenderHookLocation::BeforeContent,
            fn (RenderHookContext $context): ?View => resolve(BeforeContentTags::class, [
                'item' => $context->item ?? null,
                'tags' => $context->item['tags'] ?? null,
            ])
                ?->render(),
        );

        resolve(RenderHookRegistry::class)->register(
            RenderHookLocation::AfterTitle,
            fn (RenderHookContext $context): string|View|null => resolve(AssetAfterTitle::class, [
                'publishDate' => $context->item['publishDate'] ?? null,
                'publishDatePosition' => $context->item['publishDatePosition'] ?? null,
                'tags' => $context->item['tags'] ?? null,
                'publishDateOutput' => $context->item['publishDateOutput'] ?? null,
            ])
                ?->render(),
        );
    }

    private function registerStaticSiteExtensions(): void
    {
        if (! app()->bound(StaticSiteExtensionRegistry::class)) {
            return;
        }

        $registry = resolve(StaticSiteExtensionRegistry::class);

        if (! $registry->has('blog-tags-archives')) {
            $registry->register('blog-tags-archives', resolve(BlogStaticSiteExtension::class));
        }
    }
}
