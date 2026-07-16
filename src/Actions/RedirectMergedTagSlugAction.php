<?php

declare(strict_types=1);

namespace Capell\Blog\Actions;

use Capell\Core\Models\Language;
use Capell\Core\Models\Page;
use Capell\Tags\Data\ResolvedTagSlugData;
use Illuminate\Http\Exceptions\HttpResponseException;
use Lorisleiva\Actions\Concerns\AsFake;
use Lorisleiva\Actions\Concerns\AsObject;

final class RedirectMergedTagSlugAction
{
    use AsFake;
    use AsObject;

    public function handle(ResolvedTagSlugData $resolution, Page $tagPage, Language $language): void
    {
        if (! $resolution->shouldRedirect()) {
            return;
        }

        throw new HttpResponseException(
            redirect()->to($resolution->tag->getUrl($tagPage, $language), 301),
        );
    }
}
