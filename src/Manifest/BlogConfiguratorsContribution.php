<?php

declare(strict_types=1);

namespace Capell\Blog\Manifest;

use Capell\Core\Contracts\Extensions\ExtensionContribution;

final class BlogConfiguratorsContribution implements ExtensionContribution
{
    public static function compatibleCapellApiVersion(): string
    {
        return '^1.0';
    }
}
