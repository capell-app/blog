<?php

declare(strict_types=1);

namespace Capell\Blog\Manifest;

use Capell\Core\Contracts\Extensions\ExtensionContribution;

final class BlogModelsContribution implements ExtensionContribution
{
    public static function compatibleCapellApiVersion(): string
    {
        return '^4.0';
    }
}
