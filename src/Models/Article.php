<?php

declare(strict_types=1);

namespace Capell\Blog\Models;

use Capell\Core\Models\Page;

class Article extends Page
{
    protected $table = 'pages';

    public function getForeignKey()
    {
        return 'page_'.$this->getKeyName();
    }
}
