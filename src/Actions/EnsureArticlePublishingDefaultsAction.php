<?php

declare(strict_types=1);

namespace Capell\Blog\Actions;

use Capell\Blog\Support\Creator\BlogCreator;
use Capell\Core\Enums\LayoutEnum;
use Capell\Core\Models\Language;
use Capell\Core\Models\Layout;
use Capell\Core\Support\Creator\LayoutCreator;
use Capell\LayoutBuilder\Actions\ApplyLayoutSidebarElementContributionsAction;
use Lorisleiva\Actions\Concerns\AsFake;
use Lorisleiva\Actions\Concerns\AsObject;

/**
 * @method static void run(bool $createElements = true)
 */
class EnsureArticlePublishingDefaultsAction
{
    use AsFake;
    use AsObject;

    public function handle(bool $createElements = true): void
    {
        $blogCreator = resolve(BlogCreator::class);

        if ($createElements) {
            $articleElementType = $blogCreator->createArticleElementType();
            $blogCreator->createArticleElement($articleElementType);

            $blogCreator->createLatestArticlesElement();
            $blogCreator->createArchivesElement();
            $blogCreator->createTagsElement(Language::all());
            $blogCreator->relatedArticlesElement();

            $this->updateLayoutSidebars();
        }

        $blogCreator->createArticleLayout(createElements: $createElements);
        $blogCreator->createArchivesLayout();
        $blogCreator->createBlogPageLayout();
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

            ApplyLayoutSidebarElementContributionsAction::run($layout);
        }
    }
}
