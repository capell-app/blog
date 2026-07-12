<?php

declare(strict_types=1);

use Capell\Blog\Filament\Widgets\TrafficChartFilamentWidget;
use Capell\Tests\Support\Concerns\CreatesAdminUser;

use function Pest\Livewire\livewire;

uses(CreatesAdminUser::class)->group('widget');

it('renders for an admin user', function (): void {
    test()->actingAsAdmin();
    livewire(TrafficChartFilamentWidget::class)->assertOk();
});

it('shows site traffic heading', function (): void {
    test()->actingAsAdmin();
    livewire(TrafficChartFilamentWidget::class)
        ->assertOk()
        ->assertSee('Site traffic');
});
