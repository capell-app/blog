<?php

declare(strict_types=1);

use Capell\Blog\Support\Creator\BlogCreator;
use Capell\Core\Models\Blueprint;
use Capell\LayoutBuilder\Filament\Resources\Blocks\Pages\EditBlock;
use Capell\LayoutBuilder\Models\Block;
use Capell\Tests\Support\Concerns\CreatesAdminUser;

use function Pest\Livewire\livewire;

uses(CreatesAdminUser::class)
    ->group('block');

beforeEach(function (): void {
    test()->actingAsAdmin();
});

test('can edit related block', function (): void {
    $typeCreator = new BlogCreator;
    $block = $typeCreator->relatedArticlesBlock();

    $newData = Block::factory()->make();

    Blueprint::factory()->page()->state(['key' => 'home'])->create();

    livewire(EditBlock::class, [
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
