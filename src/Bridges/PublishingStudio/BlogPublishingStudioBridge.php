<?php

declare(strict_types=1);

namespace Capell\Blog\Bridges\PublishingStudio;

use Capell\Blog\Models\Article;
use Capell\Core\Facades\CapellCore;
use Capell\PublishingStudio\Contracts\EditorialCalendarEventContributor;
use Capell\PublishingStudio\WorkspaceRegistry;
use Illuminate\Contracts\Foundation\Application;

/**
 * The only place Blog registers itself with Publishing Studio, besides the workspace trait and calendar
 * contributor in this folder. Publishing Studio is optional, so nothing is registered unless it is available.
 */
final class BlogPublishingStudioBridge
{
    /** Shows scheduled blog articles on the editorial calendar. */
    public static function registerEditorialCalendar(Application $app): void
    {
        if (! CapellCore::isPackageAvailable('capell-app/publishing-studio')) {
            return;
        }

        $app->singleton(BlogEditorialCalendarEventContributor::class);
        $app->tag([BlogEditorialCalendarEventContributor::class], EditorialCalendarEventContributor::TAG);
    }

    /** Lets blog articles live in Publishing Studio workspaces. */
    public static function registerWorkspaceModel(): void
    {
        if (! CapellCore::isPackageAvailable('capell-app/publishing-studio')) {
            return;
        }

        WorkspaceRegistry::register(Article::class);
    }
}
