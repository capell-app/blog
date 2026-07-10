<?php

declare(strict_types=1);

namespace Capell\Blog\Actions;

use Capell\Core\Support\Security\PublicHtmlSanitizer;
use Capell\Frontend\Support\SafeHtml;
use Lorisleiva\Actions\Concerns\AsObject;

final class SanitizeBlogHtmlAction
{
    use AsObject;

    public function handle(mixed $html): SafeHtml
    {
        $sanitizer = resolve(PublicHtmlSanitizer::class);

        return SafeHtml::sanitize(
            is_string($html) ? $html : '',
            static fn (string $value): string => $sanitizer->sanitize($value),
        );
    }
}
