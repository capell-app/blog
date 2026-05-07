<?php

declare(strict_types=1);

namespace Capell\Blog\Actions;

use Capell\Blog\Support\Creator\BlogCreator;
use Capell\Core\Enums\LayoutEnum;
use Capell\Core\Models\Language;
use Capell\Core\Models\Layout;
use Capell\Core\Support\Creator\LayoutCreator;
use Lorisleiva\Actions\Concerns\AsFake;
use Lorisleiva\Actions\Concerns\AsObject;

/**
 * @method static void run(bool $createWidgets = true)
 */
class EnsureArticlePublishingDefaultsAction
{
    use AsFake;
    use AsObject;

    public function handle(bool $createWidgets = true): void
    {
        $blogCreator = resolve(BlogCreator::class);

        if ($createWidgets) {
            $articleWidgetType = $blogCreator->createArticleWidgetType();
            $blogCreator->createArticleWidget($articleWidgetType);

            $latestArticlesWidget = $blogCreator->createLatestArticlesWidget();
            $archivesWidget = $blogCreator->createArchivesWidget();
            $blogCreator->createTagsWidget(Language::all());
            $blogCreator->relatedArticlesWidget();

            $this->updateLayoutSidebars($latestArticlesWidget->key, $archivesWidget->key);
        }

        $blogCreator->createArticleLayout(createWidgets: $createWidgets);
        $blogCreator->createArchivesLayout();
        $blogCreator->createBlogPageLayout();
        $blogCreator->createTagsLayout();

        $blogCreator->createArticlePageType();
        $blogCreator->createArchivePageType();
        $blogCreator->createBlogPageType();
        $blogCreator->createTagPageType();
    }

    private function updateLayoutSidebars(string $latestArticlesWidgetKey, string $archivesWidgetKey): void
    {
        $layouts = [
            LayoutEnum::Results,
            LayoutEnum::Default,
        ];

        foreach ($layouts as $layoutKey) {
            $layout = Layout::query()->firstWhere('key', $layoutKey->value)
                ?? resolve(LayoutCreator::class)->create($layoutKey);

            $containers = $layout->getAttribute('containers');

            if (! is_array($containers)) {
                $containers = [];
            }

            $sidebarWidgets = $containers['sidebar']['widgets'] ?? [];
            $sidebarWidgetKeys = array_column($sidebarWidgets, 'widget_key');

            if (! in_array($latestArticlesWidgetKey, $sidebarWidgetKeys, true)) {
                $containers['sidebar']['widgets'] = array_values(array_filter(
                    $sidebarWidgets,
                    fn (array $widget): bool => $widget['widget_key'] !== 'latest-pages',
                ));

                $containers['sidebar']['widgets'][] = [
                    'widget_key' => $latestArticlesWidgetKey,
                ];
            }

            $sidebarWidgetKeys = array_column($containers['sidebar']['widgets'], 'widget_key');

            if ($layoutKey === LayoutEnum::Results && ! in_array($archivesWidgetKey, $sidebarWidgetKeys, true)) {
                $containers['sidebar']['widgets'][] = [
                    'widget_key' => $archivesWidgetKey,
                ];
            }

            $layout->update(['containers' => $containers]);
        }
    }
}
