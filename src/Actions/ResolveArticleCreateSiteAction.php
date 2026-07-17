<?php

declare(strict_types=1);

namespace Capell\Blog\Actions;

use Capell\Admin\Support\SiteScope;
use Capell\Core\Models\Site;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Contracts\Auth\Authenticatable;
use Lorisleiva\Actions\Concerns\AsFake;
use Lorisleiva\Actions\Concerns\AsObject;

/**
 * @method static Site run(mixed $requestedSiteId)
 */
final class ResolveArticleCreateSiteAction
{
    use AsFake;
    use AsObject;

    public function handle(mixed $requestedSiteId): Site
    {
        $actor = auth()->user();
        $message = (string) __('capell-admin::message.site_not_accessible');

        throw_unless($actor instanceof Authenticatable, AuthorizationException::class, $message);

        if (blank($requestedSiteId)) {
            $site = SiteScope::applyForCurrentActor(Site::query(), 'id', denyWhenMissingActor: true)
                ->orderByDesc('default')
                ->orderBy('id')
                ->first();

            throw_unless($site instanceof Site, AuthorizationException::class, $message);

            return $site;
        }

        $siteId = match (true) {
            is_int($requestedSiteId) => $requestedSiteId,
            is_string($requestedSiteId) && ctype_digit($requestedSiteId) => (int) $requestedSiteId,
            default => null,
        };

        throw_unless(is_int($siteId) && $siteId > 0, AuthorizationException::class, $message);

        $site = SiteScope::applyForCurrentActor(Site::query(), 'id', denyWhenMissingActor: true)->find($siteId);

        throw_unless($site instanceof Site, AuthorizationException::class, $message);

        return $site;
    }
}
