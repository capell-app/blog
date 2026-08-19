<?php

declare(strict_types=1);

namespace Capell\Blog\Http\Controllers\Concerns;

use Capell\Core\Models\SiteDomain;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;

trait ResolvesFeedSiteDomain
{
    protected function domainFor(Request $request): ?SiteDomain
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

    /**
     * @return array{0: string, 1: string}
     */
    protected function feedFormat(string $format): array
    {
        abort_unless(in_array($format, ['atom', 'rss', 'xml'], true), 404);

        return $format === 'atom'
            ? ['atom', 'application/atom+xml; charset=UTF-8']
            : ['rss', 'application/rss+xml; charset=UTF-8'];
    }
}
