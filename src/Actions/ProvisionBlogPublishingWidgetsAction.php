<?php

declare(strict_types=1);

namespace Capell\Blog\Actions;

use Capell\Blog\Data\BlogPublishingSurfaceRequestData;
use Capell\Blog\Support\Creator\BlogCreator;
use Capell\LayoutBuilder\Support\Creator\TypeCreator as LayoutTypeCreator;
use Capell\LayoutBuilder\Support\Creator\WidgetCreator;
use Lorisleiva\Actions\Concerns\AsFake;
use Lorisleiva\Actions\Concerns\AsObject;

/**
 * @internal
 *
 * @method static void run(BlogPublishingSurfaceRequestData $request)
 */
class ProvisionBlogPublishingWidgetsAction
{
    use AsFake;
    use AsObject;

    public function __construct(
        private readonly LayoutTypeCreator $layoutTypeCreator,
        private readonly WidgetCreator $widgetCreator,
    ) {}

    public function handle(BlogPublishingSurfaceRequestData $request): void
    {
        if (! $request->createWidgets) {
            return;
        }

        $blogCreator = resolve(BlogCreator::class);
        $resultsWidgetType = $this->layoutTypeCreator->resultsWidgetType();

        $blogCreator->createLatestArticlesWidget($request->languages);
        $blogCreator->createPopularArticlesWidget($request->languages);
        $blogCreator->createArchivesWidget($request->languages);
        $blogCreator->createTagsWidget($request->languages);
        $blogCreator->relatedArticlesWidget($resultsWidgetType, $request->languages);

        $this->widgetCreator->latestPagesWidget(
            $resultsWidgetType,
            $request->languagesForLegacyCreator(),
        );
    }
}
