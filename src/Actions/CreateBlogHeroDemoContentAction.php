<?php

declare(strict_types=1);

namespace Capell\Blog\Actions;

use Capell\Blog\Models\Article;
use Capell\Core\Facades\CapellCore;
use Capell\Core\Models\Layout;
use Capell\Core\Models\Page;
use Capell\Core\Models\Site;
use Capell\Core\Models\Translation;
use Capell\LayoutBuilder\Actions\AddHeroWidgetToLayoutAction;
use Capell\LayoutBuilder\Actions\CreateHeroWidgetAction;
use Capell\LayoutBuilder\Models\Widget;
use Capell\LayoutBuilder\Models\WidgetAsset;
use Capell\LayoutBuilder\Support\Creator\DemoCreator;
use Capell\LayoutBuilder\Support\Creator\TypeCreator;
use Illuminate\Database\Eloquent\Model;
use Lorisleiva\Actions\Concerns\AsFake;
use Lorisleiva\Actions\Concerns\AsObject;

final class CreateBlogHeroDemoContentAction
{
    use AsFake;
    use AsObject;

    public function handle(Site $site): void
    {
        $blogPage = $this->blogPage($site);
        $blogHeroWidget = CreateHeroWidgetAction::run(
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
        CreateHeroWidgetAction::run('article-hero', __('capell-blog::generic.article'));

        if ($blogPage instanceof Page && $blogPage->layout instanceof Layout) {
            AddHeroWidgetToLayoutAction::run($blogHeroWidget, $blogPage->layout);

            if (CapellCore::hasAsset('Section')) {
                resolve(TypeCreator::class)->createDefaultContentType();
                resolve(DemoCreator::class)->createContentsWidget($blogHeroWidget, $blogPage, 'hero');
                $this->customizeBlogHeroSlide($blogHeroWidget);
            }

            $this->applyBlogHeroMeta($blogPage);
        }

        $this->applyArticleHeroMeta($site);
    }

    private function blogPage(Site $site): ?Page
    {
        return Page::query()
            ->with(['layout', 'translations', 'blueprint'])
            ->where('site_id', $site->id)
            ->whereRelation('blueprint', 'key', 'blog')
            ->first();
    }

    private function applyBlogHeroMeta(Page $page): void
    {
        $page->translations->each(function (Model $translation): void {
            $this->mergeTranslationHero($translation, '<p>' . __('capell-blog::generic.blog_intro') . '</p>', __('capell-blog::generic.blog'));
        });
    }

    private function customizeBlogHeroSlide(Widget $widget): void
    {
        $widget->loadMissing(['assets.asset.translations', 'assets.asset.media']);

        /** @var WidgetAsset|null $slide */
        $slide = $widget->assets->first();

        if (! $slide instanceof WidgetAsset || ! $slide->asset instanceof Model) {
            return;
        }

        $widget->assets
            ->skip(1)
            ->each(fn (WidgetAsset $asset): ?bool => $asset->delete());

        $this->customizeHeroAsset($slide);
    }

    private function customizeHeroAsset(WidgetAsset $slide): void
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
