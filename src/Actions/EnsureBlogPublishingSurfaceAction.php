<?php

declare(strict_types=1);

namespace Capell\Blog\Actions;

use Capell\Blog\Data\BlogPublishingSurfaceRequestData;
use Capell\Blog\Data\BlogPublishingSurfaceResultData;
use Capell\Core\Models\Language;
use Capell\Core\Models\Site;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Lorisleiva\Actions\Concerns\AsFake;
use Lorisleiva\Actions\Concerns\AsObject;

/**
 * @method static BlogPublishingSurfaceResultData run(BlogPublishingSurfaceRequestData|Site $request, ?Collection<int, Language> $languages = null, bool $createWidgets = true)
 */
class EnsureBlogPublishingSurfaceAction
{
    use AsFake;
    use AsObject;

    /**
     * The Site overload is retained as a compatibility adapter. New callers
     * should pass BlogPublishingSurfaceRequestData.
     *
     * @param  Collection<int, Language>|null  $languages
     */
    public function handle(
        BlogPublishingSurfaceRequestData|Site $request,
        ?Collection $languages = null,
        bool $createWidgets = true,
    ): BlogPublishingSurfaceResultData {
        $surfaceRequest = $request instanceof BlogPublishingSurfaceRequestData
            ? $request
            : new BlogPublishingSurfaceRequestData(
                site: $request,
                languages: $languages,
                createWidgets: $createWidgets,
            );

        return DB::transaction(function () use ($surfaceRequest): BlogPublishingSurfaceResultData {
            ProvisionBlogPublishingWidgetsAction::run($surfaceRequest);

            $surface = ProvisionBlogPublishingPagesAction::run($surfaceRequest);

            AttachBlogPublishingSurfaceToNavigationAction::run($surfaceRequest, $surface);

            return $surface;
        });
    }
}
