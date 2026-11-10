<?php

declare(strict_types=1);

namespace Capell\Blog\Http\Controllers;

use Capell\Blog\Actions\BuildBlogFeedXmlAction;
use Capell\Blog\Http\Controllers\Concerns\ResolvesFeedSiteDomain;
use Capell\Core\Models\Site;
use Capell\Core\Models\SiteDomain;
use Capell\Core\Support\PublicRead\PublicReadProjectionHeaders;
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

        $content = BuildBlogFeedXmlAction::run($domain, $feedFormat);

        // Revalidate on every request: an entry gated after publication must not outlive its cached copy.
        return PublicReadProjectionHeaders::apply(new Response($content, 200, ['Content-Type' => $contentType]), $request, $content);
    }
}
