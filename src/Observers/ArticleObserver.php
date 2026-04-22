<?php

declare(strict_types=1);

namespace Capell\Blog\Observers;

use Capell\Blog\Models\Article;

class ArticleObserver
{
    public function saved(Article $article): void
    {
        $this->clearCache();
    }

    public function deleted(Article $article): void
    {
        // Shadow-column maintenance runs in the BelongsToWorkspace trait's
        // `deleting` hook, before this observer fires.

        $this->clearCache();
    }

    public function restored(Article $article): void
    {
        $this->clearCache();
    }

    private function clearCache(): void
    {
        // TODO
    }
}
