<?php

declare(strict_types=1);

namespace Capell\Blog\Manifest;

use Capell\Core\Contracts\Extensions\ExtensionContribution;
use Capell\Core\Contracts\Extensions\RegistersExtensionPageType;

final class BlogPageTypesContribution implements ExtensionContribution, RegistersExtensionPageType
{
    public static function compatibleCapellApiVersion(): string
    {
        return '^0.0';
    }
}
