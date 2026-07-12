<?php

declare(strict_types=1);

namespace Capell\Blog\Enums;

enum BlogLayoutEnum: string
{
    case Archives = 'archives';
    case Article = 'article';
    case BlogPage = 'blog-results';
    case TagResults = 'tag-results';
    case Tags = 'tags';
}
