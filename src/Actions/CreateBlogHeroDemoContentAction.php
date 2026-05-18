<?php

declare(strict_types=1);

namespace Capell\Blog\Actions;

use Capell\Blog\Models\Article;
use Capell\Core\Facades\CapellCore;
use Capell\Core\Models\Layout;
use Capell\Core\Models\Page;
use Capell\Core\Models\Site;
use Capell\Core\Models\Translation;
use Capell\LayoutBuilder\Actions\AddHeroBlockToLayoutAction;
use Capell\LayoutBuilder\Actions\CreateHeroBlockAction;
use Capell\LayoutBuilder\Support\Creator\DemoCreator;
use Capell\LayoutBuilder\Support\Creator\TypeCreator;
use Lorisleiva\Actions\Concerns\AsAction;

final class CreateBlogHeroDemoContentAction
{
    use AsAction;

    public function handle(Site $site): void
    {
        $blogPage = $this->blogPage($site);
        $blogHeroBlock = CreateHeroBlockAction::run('blog-hero', __('capell-blog::generic.blog'));
        CreateHeroBlockAction::run('article-hero', __('capell-blog::generic.article'));

        if ($blogPage instanceof Page && $blogPage->layout instanceof Layout) {
            AddHeroBlockToLayoutAction::run($blogHeroBlock, $blogPage->layout);

            if (CapellCore::hasAsset('Section')) {
                resolve(TypeCreator::class)->createDefaultContentType();
                resolve(DemoCreator::class)->createContentsBlock($blogHeroBlock, $blogPage, 'hero');
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
        $hero = '<h1>' . __('capell-blog::generic.latest_articles') . '</h1><p>' . __('capell-blog::generic.blog_intro') . '</p>';

        $page->translations->each(fn (Translation $translation): bool => $this->mergeTranslationHero($translation, $hero));
    }

    private function applyArticleHeroMeta(Site $site): void
    {
        Article::query()
            ->with(['translations'])
            ->where('site_id', $site->id)
            ->get()
            ->each(function (Article $article): void {
                $article->translations->each(fn (Translation $translation): bool => $this->mergeTranslationHero($translation, '<h1>' . $translation->title . '</h1>'));
            });
    }

    private function mergeTranslationHero(Translation $translation, string $hero): bool
    {
        $translation->forceFill([
            'meta' => [
                ...($translation->meta ?? []),
                'hero' => $hero,
            ],
        ])->save();

        return true;
    }
}
