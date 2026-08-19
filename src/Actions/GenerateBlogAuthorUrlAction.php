<?php

declare(strict_types=1);

namespace Capell\Blog\Actions;

use Capell\Core\Models\PageUrl;
use Lorisleiva\Actions\Concerns\AsFake;
use Lorisleiva\Actions\Concerns\AsObject;

/**
 * Build the public author archive url for a derived author slug.
 *
 * @method static string run(PageUrl $url, string $authorSlug)
 */
class GenerateBlogAuthorUrlAction
{
    use AsFake;
    use AsObject;

    public function handle(PageUrl $url, string $authorSlug): string
    {
        if (str_contains($url->full_url, '*')) {
            return str_replace('*', $authorSlug, $url->full_url);
        }

        return sprintf('%s/%s', rtrim($url->full_url, '/'), $authorSlug);
    }
}
