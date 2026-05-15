<?php

declare(strict_types=1);

namespace Capell\Blog\Enums;

use Capell\Blog\Filament\Configurators\Elements\ArticleElementConfigurator;
use Capell\Blog\Filament\Configurators\Elements\RelatedElementConfigurator;

enum ElementConfiguratorEnum: string
{
    case Article = ArticleElementConfigurator::class;

    case Related = RelatedElementConfigurator::class;
}
