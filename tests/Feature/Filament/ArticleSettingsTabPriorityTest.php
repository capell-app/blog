<?php

declare(strict_types=1);

use Capell\Blog\Filament\Components\Forms\Article\Tab\SettingsTab;
use Capell\Tests\Packages\Fixtures\PackageSurfaceContractSchemaHarness;
use Filament\Forms\Components\Select;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Tabs\Tab;
use Filament\Schemas\Schema;

it('keeps the article priority option keys locale-independent decimals while the labels follow the locale', function (string $locale, string $label): void {
    app()->setLocale($locale);

    $schema = Schema::make(new PackageSurfaceContractSchemaHarness)->operation('edit');
    $tab = $schema->components([SettingsTab::make($schema)])->getComponents()[0] ?? null;

    $this->assertInstanceOf(Tab::class, $tab);

    $section = collect($tab->getChildComponents())->first(fn (mixed $component): bool => $component instanceof Section);

    $this->assertInstanceOf(Section::class, $section);

    $priority = collect($section->getChildComponents())
        ->first(fn (mixed $component): bool => $component instanceof Select && $component->getName() === 'priority');

    $this->assertInstanceOf(Select::class, $priority);

    $options = $priority->getOptions();

    expect(array_map(strval(...), array_keys($options)))->toBe(['1.0', '0.9', '0.8', '0.7', '0.6', '0.5', '0.4', '0.3', '0.2', '0.1'])
        ->and($options['0.5'])->toBe($label);
})->with([
    'english' => ['en', '0.5'],
    'german' => ['de', '0,5'],
]);
