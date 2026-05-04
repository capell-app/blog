<?php

declare(strict_types=1);

namespace Capell\Blog\Actions;

use Capell\Blog\Data\BlogPublishingSurfaceData;
use Capell\Core\Models\Site;
use Lorisleiva\Actions\Concerns\AsFake;
use Lorisleiva\Actions\Concerns\AsObject;

/**
 * @method static BlogPublishingSurfaceData run(Site $site)
 */
class CreateBlogPagesAction
{
    use AsFake;
    use AsObject;

    public function handle(Site $site): BlogPublishingSurfaceData
    {
        return EnsureBlogPublishingSurfaceAction::run($site);
    }
}
