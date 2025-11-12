<?php

declare(strict_types=1);

namespace Capell\Blog\Enums;

use Capell\Blog\Filament\Resources\Articles\ArticleResource;
use Capell\Blog\Filament\Resources\Tags\TagResource;

enum BlogResourceEnum: string
{
    case Article = 'article';
    case Tag = 'tag';

    public function getResource(): string
    {
        return match ($this) {
            self::Article => ArticleResource::class,
            self::Tag => TagResource::class,
        };
    }
}
