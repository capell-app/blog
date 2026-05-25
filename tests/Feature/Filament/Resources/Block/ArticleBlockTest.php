<?php

declare(strict_types=1);

use Capell\Blog\Support\Creator\BlogCreator;
use Capell\LayoutBuilder\Filament\Resources\Widgets\Pages\EditWidget;
use Capell\LayoutBuilder\Filament\Resources\Widgets\Pages\ListWidgets;
use Capell\LayoutBuilder\Models\Widget;
use Capell\Tests\Support\Concerns\CreatesAdminUser;

use function Pest\Laravel\assertDatabaseHas;
use function Pest\Livewire\livewire;

uses(CreatesAdminUser::class)
    ->group('block');

beforeEach(function (): void {
    test()->actingAsAdmin();
});

test('can create article block type', function (): void {
    $newData = Widget::factory()->make();

    $typeCreator = new BlogCreator;

    $type = $typeCreator->createArticleBlockType();

    livewire(ListWidgets::class)
        ->assertSuccessful()
        ->assertCountTableRecords(0);

    Widget::query()->create([
        'name' => $newData->name,
        'key' => str($newData->name)->slug()->toString(),
        'blueprint_id' => $type->id,
        'status' => true,
    ]);

    assertDatabaseHas(Widget::class, [
        'name' => $newData->name,
        'key' => str($newData->name)->slug()->toString(),
        'blueprint_id' => $type->id,
    ]);
});

test('can edit article block', function (): void {
    $typeCreator = new BlogCreator;

    $type = $typeCreator->createArticleBlockType();

    $newData = Widget::factory()->make();

    $block = Widget::factory()->for($type)->create();

    livewire(EditWidget::class, [
        'record' => $block->getRouteKey(),
    ])
        ->assertSuccessful()
        ->fillForm([
            'name' => $newData->name,
            'key' => $newData->key,
        ])
        ->assertSchemaStateSet([
            'name' => $newData->name,
            'key' => $newData->key,
        ])
        ->assertFormFieldExists('name')
        ->assertFormFieldExists('key')
        ->call('save')
        ->assertHasNoFormErrors();

    expect($block->refresh())
        ->name->toBe($newData->name)
        ->key->toBe($newData->key);
});
