<?php

declare(strict_types=1);

namespace Capell\Blog\Listeners;

use Capell\Blog\Support\Loader\BlogLoader;
use Capell\Core\Actions\AddPageToNavigationAction;
use Capell\Core\Enums\NavigationHandle;
use Capell\Core\Events\NavigationCreating;
use Capell\Core\Models\Page;

class AddBlogPagesToNavigation
{
    private array $keys = [
        NavigationHandle::Main->value,
        NavigationHandle::Footer->value,
    ];

    public function handle(NavigationCreating $event): void
    {
        if (! in_array($event->navigation->key, $this->keys, true)) {
            return;
        }

        $blogPage = BlogLoader::getBlogPage($event->navigation->site);

        if ($blogPage instanceof Page) {
            AddPageToNavigationAction::run($blogPage, $event->navigation);
        }
    }
}
