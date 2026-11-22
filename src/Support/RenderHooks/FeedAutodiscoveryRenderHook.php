<?php

declare(strict_types=1);

namespace Capell\Blog\Support\RenderHooks;

use Capell\Blog\Data\BlogFeedLinkData;
use Capell\Core\Contracts\Rendering\RenderHookExtensionInterface;
use Capell\Core\Data\Rendering\RenderHookContext;
use Capell\Frontend\Facades\Frontend;

/**
 * Advertises the blog's RSS and Atom feeds to feed readers with
 * `<link rel="alternate">` tags, so a reader pointed at any page of a site
 * that has a blog finds the feed without being given its address.
 *
 * The links come from data prepared before render and carry nothing beyond
 * the public feed address, media type and title.
 */
final class FeedAutodiscoveryRenderHook implements RenderHookExtensionInterface
{
    public function render(RenderHookContext $context): string
    {
        $links = Frontend::getFrontendData('blog.feed_links');

        if (! is_array($links)) {
            return '';
        }

        $html = '';

        foreach ($links as $link) {
            if (! $link instanceof BlogFeedLinkData) {
                continue;
            }

            $html .= sprintf(
                '<link rel="alternate" type="%s" title="%s" href="%s">',
                e($link->type),
                e($link->title),
                e($link->href),
            );
        }

        return $html;
    }
}
