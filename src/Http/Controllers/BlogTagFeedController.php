<?php

declare(strict_types=1);

namespace Capell\Blog\Http\Controllers;

use Capell\Blog\Actions\BuildBlogFeedXmlAction;
use Capell\Blog\Http\Controllers\Concerns\ResolvesFeedSiteDomain;
use Capell\Blog\Support\Loader\TagLoader;
use Capell\Core\Models\Language;
use Capell\Core\Models\Site;
use Capell\Core\Models\SiteDomain;
use Capell\Tags\Data\ResolvedTagSlugData;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

final class BlogTagFeedController
{
    use ResolvesFeedSiteDomain;

    public function __invoke(Request $request, string $slug, string $format): Response
    {
        [$feedFormat, $contentType] = $this->feedFormat($format);

        abort_if($slug === '' || $slug === '0', 404);

        $domain = $this->domainFor($request);
        $site = $domain?->site;
        $language = $domain?->language;

        abort_unless($domain instanceof SiteDomain && $site instanceof Site && $language instanceof Language, 404);

        $resolution = TagLoader::tagPageResolution($slug, $site, $language);

        abort_unless($resolution instanceof ResolvedTagSlugData, 404);

        return new Response(
            content: BuildBlogFeedXmlAction::run($domain, $feedFormat, tag: $resolution->tag),
            status: 200,
            headers: [
                'Content-Type' => $contentType,
                'Cache-Control' => 'public, max-age=300',
            ],
        );
    }
}
