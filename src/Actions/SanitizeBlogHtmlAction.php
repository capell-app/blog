<?php

declare(strict_types=1);

namespace Capell\Blog\Actions;

use Capell\Core\Support\Security\PublicHtmlSanitizer;
use Lorisleiva\Actions\Concerns\AsObject;

final class SanitizeBlogHtmlAction
{
    use AsObject;

    public function handle(mixed $html): string
    {
        return resolve(PublicHtmlSanitizer::class)->sanitize(is_string($html) ? $html : '');
    }
}
