<?php

declare(strict_types=1);

namespace Capell\Blog\Tests\Feature;

use Capell\Blog\Providers\BlogServiceProvider;
use Capell\Blog\Tests\BlogTestCase;
use Capell\Core\Facades\CapellCore;
use Illuminate\Support\Facades\Route;
use Override;
use PHPUnit\Framework\Attributes\Test;

final class BlogFeedNotInstalledTest extends BlogTestCase
{
    #[Test]
    public function it_does_not_register_public_feed_routes_when_blog_is_not_installed(): void
    {
        $this->assertFalse(Route::has('capell.blog.feed.xml'));
        $this->assertFalse(Route::has('capell.blog.feed.rss'));
        $this->assertFalse(Route::has('capell.blog.feed.atom'));

        $this->get('/blog/feed.xml')->assertNotFound();
    }

    #[Override]
    protected function getEnvironmentSetUp(mixed $app): void
    {
        parent::getEnvironmentSetUp($app);

        CapellCore::forcePackageInstalled(BlogServiceProvider::$packageName, false);
    }
}
