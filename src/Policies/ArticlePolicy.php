<?php

declare(strict_types=1);

namespace Capell\Blog\Policies;

use Capell\Admin\Policies\Concerns\ResolvesShieldPermission;
use Capell\Admin\Support\SiteScope;
use Capell\Blog\Models\Article;
use Illuminate\Foundation\Auth\User;
use Throwable;

final class ArticlePolicy
{
    use ResolvesShieldPermission;

    private const string SUBJECT = 'Article';

    public function viewAny(User $user): bool
    {
        return $this->hasAnyPermission($user, ['view_any', 'view']);
    }

    public function view(User $user, Article $article): bool
    {
        return $this->hasAnyPermission($user, ['view_any', 'view'])
            && $this->canUseArticleSite($user, $article);
    }

    public function create(User $user): bool
    {
        return $this->hasPermission($user, 'create');
    }

    public function update(User $user, Article $article): bool
    {
        return $this->hasPermission($user, 'update')
            && $this->canUseArticleSite($user, $article);
    }

    public function delete(User $user, Article $article): bool
    {
        return $this->hasPermission($user, 'delete')
            && $this->canUseArticleSite($user, $article);
    }

    public function deleteAny(User $user): bool
    {
        return $this->hasPermission($user, 'delete_any');
    }

    public function restore(User $user, Article $article): bool
    {
        return $this->hasPermission($user, 'restore')
            && $this->canUseArticleSite($user, $article);
    }

    public function restoreAny(User $user): bool
    {
        return $this->hasPermission($user, 'restore_any');
    }

    public function forceDelete(User $user, Article $article): bool
    {
        return $this->hasPermission($user, 'force_delete')
            && $this->canUseArticleSite($user, $article);
    }

    public function forceDeleteAny(User $user): bool
    {
        return $this->hasPermission($user, 'force_delete_any');
    }

    public function replicate(User $user, Article $article): bool
    {
        return $this->hasPermission($user, 'replicate')
            && $this->canUseArticleSite($user, $article);
    }

    public function reorder(User $user): bool
    {
        return $this->hasPermission($user, 'reorder');
    }

    /**
     * @param  list<string>  $abilities
     */
    private function hasAnyPermission(User $user, array $abilities): bool
    {
        foreach ($abilities as $ability) {
            if ($this->hasPermission($user, $ability)) {
                return true;
            }
        }

        return false;
    }

    private function hasPermission(User $user, string $ability): bool
    {
        if (SiteScope::isGlobalActor($user)) {
            return true;
        }

        if (! method_exists($user, 'checkPermissionTo')) {
            return false;
        }

        try {
            return $user->checkPermissionTo(self::permission($ability, self::SUBJECT));
        } catch (Throwable) {
            return false;
        }
    }

    private function canUseArticleSite(User $user, Article $article): bool
    {
        if ($article->site_id === null || SiteScope::isGlobalActor($user)) {
            return true;
        }

        if (method_exists($user, 'getAssignedSiteIds')) {
            return $user->getAssignedSiteIds()->contains($article->site_id);
        }

        return true;
    }
}
