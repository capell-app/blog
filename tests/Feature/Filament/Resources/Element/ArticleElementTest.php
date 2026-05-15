<?php

declare(strict_types=1);

use Capell\Blog\Support\Creator\BlogCreator;
use Capell\LayoutBuilder\Filament\Resources\Elements\Pages\EditElement;
use Capell\LayoutBuilder\Filament\Resources\Elements\Pages\ListElements;
use Capell\LayoutBuilder\Models\Element;
use Capell\Tests\Support\Concerns\CreatesAdminUser;

use function Pest\Laravel\assertDatabaseHas;
use function Pest\Livewire\livewire;

uses(CreatesAdminUser::class)
    ->group('element');

beforeEach(function (): void {
    test()->actingAsAdmin();
});

test('can create article element type', function (): void {
    $newData = Element::factory()->make();

    $typeCreator = new BlogCreator;

    $type = $typeCreator->createArticleElementType();

    livewire(ListElements::class)
        ->assertSuccessful()
        ->assertCountTableRecords(0);

    Element::query()->create([
        'name' => $newData->name,
        'key' => str($newData->name)->slug()->toString(),
        'blueprint_id' => $type->id,
        'status' => true,
    ]);

    assertDatabaseHas(Element::class, [
        'name' => $newData->name,
        'key' => str($newData->name)->slug()->toString(),
        'blueprint_id' => $type->id,
    ]);
});

test('can edit article element', function (): void {
    $typeCreator = new BlogCreator;

    $type = $typeCreator->createArticleElementType();

    $newData = Element::factory()->make();

    $element = Element::factory()->for($type)->create();

    livewire(EditElement::class, [
        'record' => $element->getRouteKey(),
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

    expect($element->refresh())
        ->name->toBe($newData->name)
        ->key->toBe($newData->key);
});
