<?php

declare(strict_types=1);

namespace Capell\Blog\Manifest;

use Capell\Admin\Policies\Concerns\ResolvesShieldPermission;
use Capell\Core\Contracts\Extensions\ExtensionContribution;
use Capell\Core\Contracts\Extensions\RegistersExtensionPermission;
use RuntimeException;

final class BlogPermissionsContribution implements ExtensionContribution, RegistersExtensionPermission
{
    use ResolvesShieldPermission;

    /**
     * Resolve subject/ability descriptors at runtime because the host controls
     * Shield's case and separator; a manifest cannot contain concrete keys.
     *
     * @return list<string>
     */
    public static function permissions(): array
    {
        /** @var array{contributes: list<array{class: string, subjects?: array<string, list<string>>}>} $manifest */
        $manifest = json_decode((string) file_get_contents(dirname(__DIR__, 2) . '/capell.json'), true, flags: JSON_THROW_ON_ERROR);

        foreach ($manifest['contributes'] as $contribution) {
            if ($contribution['class'] !== self::class) {
                continue;
            }

            $permissions = [];
            foreach ($contribution['subjects'] ?? [] as $subject => $abilities) {
                foreach ($abilities as $ability) {
                    $permissions[] = self::permission($ability, $subject);
                }
            }

            return $permissions;
        }

        throw new RuntimeException('Blog permission descriptors are missing from the package manifest.');
    }

    public static function compatibleCapellApiVersion(): string
    {
        return '^1.0';
    }
}
