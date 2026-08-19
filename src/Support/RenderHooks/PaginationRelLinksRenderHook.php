<?php

declare(strict_types=1);

namespace Capell\Blog\Support\RenderHooks;

use Capell\Frontend\Contracts\RenderHookExtensionInterface;
use Capell\Frontend\Data\RenderHookContext;
use Capell\Frontend\Facades\Frontend;
use Illuminate\Contracts\Pagination\Paginator;
use Illuminate\Routing\Route;
use MichalOravec\PaginateRoute\PaginateRoute;

/**
 * Emits `<link rel="prev">` / `<link rel="next">` for paginated blog listings
 * (blog index, date archive, tag archive).
 *
 * Pagination is path-based (michaloravec/laravel-paginateroute), so the
 * paginator built by the frontend listing action carries no public path and
 * its own `url()` cannot be trusted. URLs are therefore derived from the
 * current route via `PaginateRoute::pageUrl()`.
 *
 * The vendor `renderRelLinks()` helper is deliberately not used: it emits raw,
 * unescaped hrefs and materialises every page URL just to pick two of them.
 */
final class PaginationRelLinksRenderHook implements RenderHookExtensionInterface
{
    public function render(RenderHookContext $context): string
    {
        $paginator = Frontend::getFrontendData('blog.results');

        if (! $paginator instanceof Paginator || ! $paginator->hasPages()) {
            return '';
        }

        $paginateRoute = app('paginateroute');

        if (! $paginateRoute instanceof PaginateRoute) {
            return '';
        }

        if (! request()->route() instanceof Route) {
            return '';
        }

        $links = '';

        if ($paginateRoute->hasPreviousPage()) {
            $previousUrl = $paginateRoute->previousPageUrl();

            if (is_string($previousUrl) && $previousUrl !== '') {
                $links .= sprintf('<link rel="prev" href="%s">', e($previousUrl));
            }
        }

        if ($paginateRoute->hasNextPage($paginator)) {
            $nextUrl = $paginateRoute->nextPageUrl($paginator);

            if (is_string($nextUrl) && $nextUrl !== '') {
                $links .= sprintf('<link rel="next" href="%s">', e($nextUrl));
            }
        }

        return $links;
    }
}
