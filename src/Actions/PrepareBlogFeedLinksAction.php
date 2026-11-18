<?php

declare(strict_types=1);

namespace Capell\Blog\Actions;

use Capell\Blog\Data\BlogFeedLinkData;
use Capell\Blog\Http\Controllers\Concerns\ResolvesFeedSiteDomain;
use Capell\Blog\Support\Loader\BlogLoader;
use Capell\Core\Models\Language;
use Capell\Core\Models\Site;
use Capell\Core\Models\SiteDomain;
use Capell\Frontend\Contracts\FrontendContextReader;
use Illuminate\Http\Request;
use Lorisleiva\Actions\Concerns\AsFake;
use Lorisleiva\Actions\Concerns\AsObject;

/**
 * Prepares the feed links the head advertises, so the render hook reads
 * hydrated data instead of querying during the guarded Blade render.
 *
 * Feeds are served per requested domain, so the links resolve the same domain
 * the feed controllers do. A site without a blog page advertises nothing.
 */
final class PrepareBlogFeedLinksAction
{
    use AsFake;
    use AsObject;
    use ResolvesFeedSiteDomain;

    public function handle(FrontendContextReader $context, Site $site, Language $language, Request $request): void
    {
        if ($context->getFrontendData('blog.feed_links') !== null) {
            return;
        }

        $context->setFrontendData('blog.feed_links', $this->links($site, $language, $request));
    }

    /**
     * @return list<BlogFeedLinkData>
     */
    private function links(Site $site, Language $language, Request $request): array
    {
        $domain = $this->domainFor($request);

        if (! $domain instanceof SiteDomain || $domain->site_id !== $site->getKey()) {
            return [];
        }

        if (BlogLoader::getBlogPageUrl($site, $language, false) === '') {
            return [];
        }

        $feeds = resolve(BuildBlogFeedXmlAction::class);
        $title = $feeds->feedTitle($domain);

        return [
            new BlogFeedLinkData('application/rss+xml', $title, $feeds->feedUrl($domain, 'xml')),
            new BlogFeedLinkData('application/atom+xml', $title, $feeds->feedUrl($domain, 'atom')),
        ];
    }
}
