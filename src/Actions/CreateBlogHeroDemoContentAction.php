<?php

declare(strict_types=1);

namespace Capell\Blog\Actions;

use Capell\Blog\Models\Article;
use Capell\Core\Enums\MediaCollectionEnum;
use Capell\Core\Facades\CapellCore;
use Capell\Core\Models\Layout;
use Capell\Core\Models\Page;
use Capell\Core\Models\Site;
use Capell\Core\Models\Translation;
use Capell\LayoutBuilder\Actions\AddHeroBlockToLayoutAction;
use Capell\LayoutBuilder\Actions\CreateHeroBlockAction;
use Capell\LayoutBuilder\Models\Widget;
use Capell\LayoutBuilder\Models\WidgetAsset;
use Capell\LayoutBuilder\Support\Creator\DemoCreator;
use Capell\LayoutBuilder\Support\Creator\TypeCreator;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\File;
use Lorisleiva\Actions\Concerns\AsAction;
use Spatie\MediaLibrary\HasMedia;

final class CreateBlogHeroDemoContentAction
{
    use AsAction;

    public function handle(Site $site): void
    {
        $blogPage = $this->blogPage($site);
        $blogHeroBlock = CreateHeroBlockAction::run(
            'blog-hero',
            __('capell-blog::generic.blog'),
            'small',
            [
                'background_color' => '#f8fafc',
                'carousel_arrows' => false,
                'carousel_auto_play' => false,
                'carousel_pagination' => false,
                'color' => 'light',
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
        );
        CreateHeroBlockAction::run('article-hero', __('capell-blog::generic.article'));

        if ($blogPage instanceof Page && $blogPage->layout instanceof Layout) {
            AddHeroBlockToLayoutAction::run($blogHeroBlock, $blogPage->layout);

            if (CapellCore::hasAsset('Section')) {
                resolve(TypeCreator::class)->createDefaultContentType();
                resolve(DemoCreator::class)->createContentsBlock($blogHeroBlock, $blogPage, 'hero');
                $this->customizeBlogHeroSlide($blogHeroBlock, $blogPage);
            }

            $this->applyBlogHeroMeta($blogPage);
        }

        $this->applyArticleHeroMeta($site);
    }

    private function blogPage(Site $site): ?Page
    {
        return Page::query()
            ->with(['layout', 'translations', 'type'])
            ->where('site_id', $site->id)
            ->whereRelation('type', 'key', 'blog')
            ->first();
    }

    private function applyBlogHeroMeta(Page $page): void
    {
        $page->translations->each(function (Model $translation): void {
            $this->mergeTranslationHero($translation, '<p>' . __('capell-blog::generic.blog_intro') . '</p>', __('capell-blog::generic.blog'));
        });
    }

    private function customizeBlogHeroSlide(Widget $block, Page $page): void
    {
        $block->loadMissing(['assets.asset.translations', 'assets.asset.media']);

        /** @var WidgetAsset|null $slide */
        $slide = $block->assets->first();

        if (! $slide instanceof WidgetAsset || ! $slide->asset instanceof Model) {
            return;
        }

        $block->assets
            ->skip(1)
            ->each(fn (WidgetAsset $asset): ?bool => $asset->delete());

        $this->customizeHeroAsset($slide, $page);
    }

    private function customizeHeroAsset(WidgetAsset $slide, Page $page): void
    {
        $asset = $slide->asset;

        if (! $asset instanceof Model) {
            return;
        }

        $asset->forceFill([
            'name' => __('capell-blog::generic.blog'),
            'meta' => [
                ...($asset->meta ?? []),
                'actions' => [],
                'color' => 'light',
            ],
        ])->save();

        $asset->translations->each(function (Model $translation): void {
            if (! $translation instanceof Translation) {
                return;
            }

            $translation->forceFill([
                'title' => __('capell-blog::generic.blog'),
                'content' => '<p>' . __('capell-blog::generic.blog_intro') . '</p>',
            ])->save();
        });

        $this->replaceHeroImageWithSpaniel($asset);
    }

    private function replaceHeroImageWithSpaniel(Model $asset): void
    {
        if (! $asset instanceof HasMedia) {
            return;
        }

        $spanielImage = '/Users/ben/Sites/packages/capell/capell-packages-4/packages/demo-kit/demo/img/springer-spaniel.jpg';

        if (! File::exists($spanielImage)) {
            return;
        }

        $asset->clearMediaCollection(MediaCollectionEnum::Image->value);
        $asset->addMedia($spanielImage)
            ->preservingOriginal()
            ->toMediaCollection(MediaCollectionEnum::Image->value);
    }

    private function applyArticleHeroMeta(Site $site): void
    {
        Article::query()
            ->with(['translations'])
            ->where('site_id', $site->id)
            ->get()
            ->each(function (Article $article): void {
                $article->translations->each(function (Model $translation): void {
                    $this->mergeArticleTranslationHero($translation);
                });
            });
    }

    private function mergeArticleTranslationHero(Model $translation): bool
    {
        if (! $translation instanceof Translation) {
            return false;
        }

        return $this->mergeTranslationHero($translation, '<h1>' . $translation->title . '</h1>');
    }

    private function mergeTranslationHero(Model $translation, string $hero, ?string $heroTitle = null): bool
    {
        if (! $translation instanceof Translation) {
            return false;
        }

        $meta = [
            ...($translation->meta ?? []),
            'hero' => $hero,
        ];

        if ($heroTitle !== null) {
            $meta['hero_title'] = $heroTitle;
        }

        $translation->forceFill([
            'meta' => $meta,
        ])->save();

        return true;
    }
}
