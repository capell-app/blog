<?php

declare(strict_types=1);

namespace Capell\Blog\Enums;

enum BlockComponentEnum: string
{
    case Archives = 'capell-blog::block.page.archives';
    case Article = 'capell-blog::block.page.article';
    case PageRelated = 'capell-blog::block.page.related';
    case Tags = 'capell-blog::block.tag.tags';
}
