<?php

declare(strict_types=1);

namespace Capell\Blog\Manifest;

use Capell\Core\Contracts\Extensions\ExtensionContribution;
use Capell\Core\Contracts\Extensions\RegistersExtensionRenderHook;

final class BlogRenderHooksContribution implements ExtensionContribution, RegistersExtensionRenderHook
{
    public static function compatibleCapellApiVersion(): string
    {
        return '^1.0';
    }
}
