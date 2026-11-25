<?php

declare(strict_types=1);

namespace Capell\Blog\Bridges\Navigation;

use Capell\Core\Contracts\Pageable;
use Capell\Core\Facades\CapellCore;
use Capell\Core\Models\Language;
use Capell\Core\Models\Site;
use Capell\Core\Support\TableName;
use Capell\Navigation\Actions\AddPageToNavigationAction;
use Capell\Navigation\Enums\NavigationHandle;
use Capell\Navigation\Events\NavigationCreating;
use Capell\Navigation\Models\Navigation;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Schema;

/**
 * The only place Blog names Navigation classes outside AddBlogPagesToNavigation. Navigation is
 * optional, so every method does nothing unless it is installed and its tables exist.
 */
final class BlogNavigationBridge
{
    /** Lists blog pages in the main and footer menus when a navigation is created. */
    public static function registerListener(): void
    {
        if (! CapellCore::isPackageAvailable('capell-app/navigation')) {
            return;
        }

        if (! CapellCore::isPackageInstalled('capell-app/navigation')) {
            return;
        }

        Event::listen(NavigationCreating::class, AddBlogPagesToNavigation::class);
    }

    /** @return list<string> The handles of the menus a blog page is listed in, or none without Navigation. */
    public static function blogMenuHandles(): array
    {
        if (! CapellCore::isPackageAvailable('capell-app/navigation')) {
            return [];
        }

        return [NavigationHandle::Main->value, NavigationHandle::Footer->value];
    }

    /**
     * @param  array<array-key, mixed>  $keys
     * @param  iterable<array-key, Pageable>  $pages
     * @param  Collection<int, Language>  $languages
     */
    public static function addPages(array $keys, Site $site, iterable $pages, Collection $languages): void
    {
        if (! CapellCore::isPackageAvailable('capell-app/navigation')) {
            return;
        }

        if (! CapellCore::isPackageInstalled('capell-app/navigation')) {
            return;
        }

        if (! Schema::hasTable(TableName::resolve('navigations'))) {
            return;
        }

        Navigation::query()
            ->whereIn('key', $keys)
            ->where(
                fn (Builder $query) => $query->whereNull('site_id')
                    ->orWhere('site_id', $site->id),
            )
            ->where(
                fn (Builder $query) => $query->whereNull('language_id')
                    ->orWhereIn('language_id', $languages->pluck('id')),
            )
            ->get()
            ->each(function (Navigation $navigation) use ($pages): void {
                foreach ($pages as $page) {
                    AddPageToNavigationAction::run($page, $navigation);
                }
            });
    }

    /** Loads the site's navigations onto it once, so rendering does not query them per widget. */
    public static function hydrateSiteNavigations(Site $site): void
    {
        if ($site->relationLoaded('navigations')) {
            return;
        }

        if (! CapellCore::isPackageAvailable('capell-app/navigation')) {
            return;
        }

        if (! CapellCore::isPackageInstalled('capell-app/navigation')) {
            return;
        }

        if (! Schema::hasTable(TableName::resolve('navigations'))) {
            return;
        }

        $site->setRelation(
            'navigations',
            Navigation::query()
                ->where(fn (Builder $query): Builder => $query
                    ->where('site_id', $site->getKey())
                    ->orWhereNull('site_id'))
                ->publishedDate()
                ->get(),
        );
    }
}
