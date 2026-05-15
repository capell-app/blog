<?php

declare(strict_types=1);

use Capell\Blog\Support\Creator\BlogCreator;
use Capell\Core\Models\Blueprint;
use Capell\LayoutBuilder\Filament\Resources\Elements\Pages\EditElement;
use Capell\LayoutBuilder\Models\Element;
use Capell\Tests\Support\Concerns\CreatesAdminUser;

use function Pest\Livewire\livewire;

uses(CreatesAdminUser::class)
    ->group('element');

beforeEach(function (): void {
    test()->actingAsAdmin();
});

test('can edit related element', function (): void {
    $typeCreator = new BlogCreator;
    $element = $typeCreator->relatedArticlesElement();

    $newData = Element::factory()->make();

    Blueprint::factory()->page()->state(['key' => 'home'])->create();

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
