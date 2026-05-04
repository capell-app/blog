<?php

declare(strict_types=1);

namespace Capell\Blog\Actions;

use Capell\Core\Models\Site;
use Lorisleiva\Actions\Concerns\AsFake;
use Lorisleiva\Actions\Concerns\AsObject;

/**
 * @method static void run()
 */
class InstallPackageAction
{
    use AsFake;
    use AsObject;

    public function handle(): void
    {
        EnsureArticlePublishingDefaultsAction::run();

        Site::with('languages')->each(function (Site $site): void {
            EnsureBlogPublishingSurfaceAction::run($site);
        });
    }
}
