<?php

declare(strict_types=1);

namespace Capell\Blog\Data;

use Capell\Core\Models\Page;
use Spatie\LaravelData\Data;

class BlogPublishingSurfaceData extends Data
{
    public function __construct(
        public Page $blogPage,
        public Page $archivesPage,
        public Page $archivePage,
        public Page $tagsPage,
        public Page $tagPage,
    ) {}
}
