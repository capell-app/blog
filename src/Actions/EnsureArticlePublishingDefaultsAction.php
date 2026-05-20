<?php

declare(strict_types=1);

namespace Capell\Blog\Actions;

use Capell\Blog\Support\Creator\BlogCreator;
use Capell\Core\Enums\LayoutEnum;
use Capell\Core\Models\Language;
use Capell\Core\Models\Layout;
use Capell\Core\Support\Creator\LayoutCreator;
use Capell\LayoutBuilder\Actions\ApplyLayoutSidebarBlockContributionsAction;
use Lorisleiva\Actions\Concerns\AsFake;
use Lorisleiva\Actions\Concerns\AsObject;

/**
 * @method static void run(bool $createBlocks = true)
 */
class EnsureArticlePublishingDefaultsAction
{
    use AsFake;
    use AsObject;

    public function handle(bool $createBlocks = true): void
    {
        $blogCreator = resolve(BlogCreator::class);

        if ($createBlocks) {
            $articleBlockType = $blogCreator->createArticleBlockType();
            $blogCreator->createArticleBlock($articleBlockType);

            $blogCreator->createLatestArticlesBlock();
            $blogCreator->createArchivesBlock();
            $blogCreator->createTagsBlock(Language::all());
            $blogCreator->relatedArticlesBlock();

            $this->updateLayoutSidebars();
        }

        $blogCreator->createArticleLayout(createBlocks: $createBlocks);
        $blogCreator->createArchivesLayout();
        $blogCreator->createBlogPageLayout();
        $blogCreator->createTagResultsLayout();
        $blogCreator->createTagsLayout();

        $blogCreator->createArticlePageType();
        $blogCreator->createArchivePageType();
        $blogCreator->createBlogPageType();
        $blogCreator->createTagPageType();
    }

    private function updateLayoutSidebars(): void
    {
        $layouts = [
            LayoutEnum::Results,
            LayoutEnum::Default,
        ];

        foreach ($layouts as $layoutKey) {
            $layout = Layout::query()->firstWhere('key', $layoutKey->value)
                ?? resolve(LayoutCreator::class)->create($layoutKey);

            ApplyLayoutSidebarBlockContributionsAction::run($layout);
        }
    }
}
