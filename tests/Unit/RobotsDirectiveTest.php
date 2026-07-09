<?php

declare(strict_types=1);

use Capell\Blog\Enums\RobotsDirective;

it('provides translated labels for article robots directives', function (): void {
    expect(RobotsDirective::NoIndex->value)->toBe('noindex')
        ->and(RobotsDirective::NoIndex->getLabel())->toBe(__('capell-admin::form.noindex'))
        ->and(RobotsDirective::NoFollow->value)->toBe('nofollow')
        ->and(RobotsDirective::NoFollow->getLabel())->toBe(__('capell-admin::form.nofollow'));
});
