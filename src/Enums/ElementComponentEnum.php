<?php

declare(strict_types=1);

namespace Capell\Blog\Enums;

enum ElementComponentEnum: string
{
    case Archives = 'capell-blog::element.page.archives';
    case Article = 'capell-blog::element.page.article';
    case PageRelated = 'capell-blog::element.page.related';
    case Tags = 'capell-blog::element.tag.tags';
}
