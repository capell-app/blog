<?php

declare(strict_types=1);

namespace Capell\Blog\Data;

use Spatie\LaravelData\Data;

/**
 * Public description of one feed advertised in the page head: its media type,
 * human title and absolute address, and nothing else.
 */
final class BlogFeedLinkData extends Data
{
    public function __construct(
        public string $type,
        public string $title,
        public string $href,
    ) {}
}
