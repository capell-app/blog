<?php

declare(strict_types=1);

namespace Capell\Blog\Data;

use Spatie\LaravelData\Data;

final class BlogWidgetContentData extends Data
{
    public function __construct(
        public readonly bool $show = false,
        public readonly ?string $title = null,
        public readonly ?string $content = null,
        public readonly mixed $contentType = null,
        public readonly mixed $divider = null,
        public readonly mixed $textAlign = null,
        public readonly mixed $headingStyle = null,
        public readonly bool $muted = false,
        public readonly ?string $headingTag = null,
    ) {}
}
