<?php

declare(strict_types=1);

use Capell\Blog\Support\Creator\BlogCreator;
use Capell\LayoutBuilder\Filament\Resources\Blocks\Pages\EditBlock;
use Capell\LayoutBuilder\Filament\Resources\Blocks\Pages\ListBlocks;
use Capell\LayoutBuilder\Models\Block;
use Capell\Tests\Support\Concerns\CreatesAdminUser;

use function Pest\Laravel\assertDatabaseHas;
use function Pest\Livewire\livewire;

uses(CreatesAdminUser::class)
    ->group('block');

beforeEach(function (): void {
    test()->actingAsAdmin();
});

test('can create article block type', function (): void {
    $newData = Block::factory()->make();

    $typeCreator = new BlogCreator;

    $type = $typeCreator->createArticleBlockType();

    livewire(ListBlocks::class)
        ->assertSuccessful()
        ->assertCountTableRecords(0);

    Block::query()->create([
        'name' => $newData->name,
        'key' => str($newData->name)->slug()->toString(),
        'blueprint_id' => $type->id,
        'status' => true,
    ]);

    assertDatabaseHas(Block::class, [
        'name' => $newData->name,
        'key' => str($newData->name)->slug()->toString(),
        'blueprint_id' => $type->id,
    ]);
});

test('can edit article block', function (): void {
    $typeCreator = new BlogCreator;

    $type = $typeCreator->createArticleBlockType();

    $newData = Block::factory()->make();

    $block = Block::factory()->for($type)->create();

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
