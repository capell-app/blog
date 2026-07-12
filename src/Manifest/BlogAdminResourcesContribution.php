<?php

declare(strict_types=1);

namespace Capell\Blog\Manifest;

use Capell\Core\Contracts\Extensions\ExtensionContribution;
use Capell\Core\Contracts\Extensions\RegistersExtensionAdminResource;

final class BlogAdminResourcesContribution implements ExtensionContribution, RegistersExtensionAdminResource
{
    public static function compatibleCapellApiVersion(): string
    {
        return '^4.0';
    }
}
