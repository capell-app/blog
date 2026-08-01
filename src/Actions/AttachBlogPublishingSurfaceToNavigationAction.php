<?php

declare(strict_types=1);

namespace Capell\Blog\Actions;

use Capell\Blog\Data\BlogPublishingSurfaceRequestData;
use Capell\Blog\Data\BlogPublishingSurfaceResultData;
use Capell\Blog\Support\Creator\BlogCreator;
use Capell\Core\Facades\CapellCore;
use Capell\Navigation\Enums\NavigationHandle;
use Illuminate\Support\Facades\Schema;
use Lorisleiva\Actions\Concerns\AsFake;
use Lorisleiva\Actions\Concerns\AsObject;

/**
 * @internal
 *
 * @method static void run(BlogPublishingSurfaceRequestData $request, BlogPublishingSurfaceResultData $surface)
 */
class AttachBlogPublishingSurfaceToNavigationAction
{
    use AsFake;
    use AsObject;

    public function handle(
        BlogPublishingSurfaceRequestData $request,
        BlogPublishingSurfaceResultData $surface,
    ): void {
        if (! CapellCore::isPackageAvailable('capell-app/navigation')) {
            return;
        }

        if (! Schema::hasTable('navigations')) {
            return;
        }

        resolve(BlogCreator::class)->addPagesToNavigations(
            [NavigationHandle::Main->value, NavigationHandle::Footer->value],
            site: $request->site,
            pages: [$surface->blogPage],
            languages: $request->languages,
        );
    }
}
