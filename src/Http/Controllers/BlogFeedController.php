<?php

declare(strict_types=1);

namespace Capell\Blog\Http\Controllers;

use Capell\Blog\Actions\BuildBlogFeedXmlAction;
use Capell\Core\Models\Site;
use Capell\Core\Models\SiteDomain;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

final class BlogFeedController
{
    public function __invoke(Request $request, string $format): Response
    {
        abort_unless(in_array($format, ['atom', 'rss', 'xml'], true), 404);

        $feedFormat = $format === 'atom' ? 'atom' : 'rss';
        $domain = $this->domainFor($request);

        abort_unless($domain instanceof SiteDomain && $domain->site instanceof Site && $domain->language !== null, 404);

        return response(
            content: BuildBlogFeedXmlAction::run($domain, $feedFormat),
            status: 200,
            headers: [
                'Content-Type' => $feedFormat === 'atom'
                    ? 'application/atom+xml; charset=UTF-8'
                    : 'application/rss+xml; charset=UTF-8',
                'Cache-Control' => 'public, max-age=300',
            ],
        );
    }

    private function domainFor(Request $request): ?SiteDomain
    {
        $host = $request->getHost();

        return SiteDomain::query()
            ->with(['site', 'language'])
            ->where('status', true)
            ->where(function (Builder $query) use ($host): void {
                $query->where('domain', $host)
                    ->orWhereNull('domain');
            })
            ->orderByRaw('CASE WHEN domain = ? THEN 0 ELSE 1 END', [$host])
            ->default()
            ->first();
    }
}
