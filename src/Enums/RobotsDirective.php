<?php

declare(strict_types=1);

namespace Capell\Blog\Enums;

use Filament\Support\Contracts\HasLabel;

enum RobotsDirective: string implements HasLabel
{
    case NoIndex = 'noindex';
    case NoFollow = 'nofollow';

    public function getLabel(): string
    {
        return match ($this) {
            self::NoIndex => __('capell-admin::form.noindex'),
            self::NoFollow => __('capell-admin::form.nofollow'),
        };
    }
}
