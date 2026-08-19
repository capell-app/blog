<?php

declare(strict_types=1);

namespace Capell\Blog\Http\Controllers;

use Capell\Blog\Actions\BuildBlogFeedXmlAction;
use Capell\Blog\Http\Controllers\Concerns\ResolvesFeedSiteDomain;
use Capell\Core\Models\Site;
use Capell\Core\Models\SiteDomain;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

final class BlogFeedController
{
    use ResolvesFeedSiteDomain;

    public function __invoke(Request $request, string $format): Response
    {
        [$feedFormat, $contentType] = $this->feedFormat($format);

        $domain = $this->domainFor($request);

        abort_unless($domain instanceof SiteDomain && $domain->site instanceof Site && $domain->language !== null, 404);

        return new Response(
            content: BuildBlogFeedXmlAction::run($domain, $feedFormat),
            status: 200,
            headers: [
                'Content-Type' => $contentType,
                'Cache-Control' => 'public, max-age=300',
            ],
        );
    }
}
