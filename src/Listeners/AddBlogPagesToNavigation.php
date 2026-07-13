<?php

declare(strict_types=1);

namespace Capell\Blog\Listeners;

use Capell\Blog\Support\Loader\BlogLoader;
use Capell\Core\Contracts\Pageable;
use Capell\Core\Facades\CapellCore;
use Capell\Core\Models\Site;
use Capell\Navigation\Actions\AddPageToNavigationAction;
use Capell\Navigation\Events\NavigationCreating;
use Illuminate\Support\Facades\Schema;

class AddBlogPagesToNavigation
{
    /** @var list<string> */
    private array $keys = ['main', 'footer'];

    public function handle(NavigationCreating $event): void
    {
        if (
            ! CapellCore::isPackageInstalled('capell-app/navigation')
            || ! Schema::hasTable('navigations')
            || ! class_exists(AddPageToNavigationAction::class)
        ) {
            return;
        }

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
