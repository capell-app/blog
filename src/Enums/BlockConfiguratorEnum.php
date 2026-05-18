<?php

declare(strict_types=1);

namespace Capell\Blog\Enums;

use Capell\Blog\Filament\Configurators\Blocks\ArticleBlockConfigurator;
use Capell\Blog\Filament\Configurators\Blocks\RelatedBlockConfigurator;

enum BlockConfiguratorEnum: string
{
    case Article = ArticleBlockConfigurator::class;

    case Related = RelatedBlockConfigurator::class;
}
