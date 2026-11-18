<?php

declare(strict_types=1);

namespace Capell\Blog\Bridges;

use Capell\Core\Facades\CapellCore;
use Capell\Search\Actions\FlushSiteDiscoverySearchRegistryAction;
use Illuminate\Support\Facades\DB;

/**
 * The one place Blog touches the optional Search package. Search is a suggested dependency, so
 * nothing outside src/Bridges may import it.
 */
final class BlogSearchBridge
{
    /**
     * Search keeps a cached page index for 60 seconds. When it is installed, an article that was published,
     * unpublished or edited must be searchable (or gone) on the next request rather than after that expiry.
     */
    public static function flushRegistry(): void
    {
        if (! CapellCore::isPackageAvailable('capell-app/search')) {
            return;
        }

        if (! CapellCore::isPackageInstalled('capell-app/search')) {
            return;
        }

        // After commit, so a search cannot rebuild its index from pre-commit data and cache it under the new version.
        DB::afterCommit(static fn () => FlushSiteDiscoverySearchRegistryAction::run());
    }
}
