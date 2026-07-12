<?php

declare(strict_types=1);

namespace Capell\Blog\Listeners;

use Capell\Blog\Support\Loader\BlogLoader;
use Capell\Core\Contracts\Pageable;
use Capell\Core\Models\Site;
use Capell\Navigation\Actions\AddPageToNavigationAction;
use Capell\Navigation\Enums\NavigationHandle;
use Capell\Navigation\Events\NavigationCreating;

class AddBlogPagesToNavigation
{
    /**
     * @var array<array-key, mixed>
     */
    private array $keys = [
        NavigationHandle::Main->value,
        NavigationHandle::Footer->value,
    ];

    public function handle(NavigationCreating $event): void
    {
        if (! in_array($event->navigation->key, $this->keys, true)) {
            return;
        }

        $site = $event->navigation->site;
        if (! $site instanceof Site) {
            return;
        }

        $blogPage = BlogLoader::getBlogPage($site);

        if ($blogPage instanceof Pageable) {
            AddPageToNavigationAction::run($blogPage, $event->navigation);
        }
    }
}
