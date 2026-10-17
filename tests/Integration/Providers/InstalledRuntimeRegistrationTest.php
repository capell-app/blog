<?php

declare(strict_types=1);

use Capell\Blog\Enums\LivewirePageComponentEnum;
use Capell\Core\Enums\RenderableTypeEnum;
use Capell\Core\Support\Renderables\RenderableRegistry;
use Capell\Tests\Support\InstalledRuntimeParity;
use Capell\Tests\Support\InstalledRuntimeReceiptWitness;
use Capell\Tests\Support\InstalledRuntimeState;
use Capell\Tests\Support\InstalledRuntimeTestCase;

it('registers each blog page renderable once across runtime and frontend providers', function (): void {
    InstalledRuntimeState::run(function (): void {
        $app = InstalledRuntimeTestCase::boot(InstalledRuntimeParity::manifest('blog'), true);
        $emissions = $app->make(InstalledRuntimeReceiptWitness::class)->emissions();
        $registry = $app->make(RenderableRegistry::class);

        foreach (LivewirePageComponentEnum::cases() as $component) {
            if ($component->getComponent() === null || $component->getComponent() === '') {
                continue;
            }

            expect($registry->get(RenderableTypeEnum::Page, $component->value)->livewire)->toBe($component->value)
                ->and(array_filter($emissions, static fn (array $emission): bool => $emission[1] === 'renderable:page:' . $component->value))->toHaveCount(1);
        }
    });
});
