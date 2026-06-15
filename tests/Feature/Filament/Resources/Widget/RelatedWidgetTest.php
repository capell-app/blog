<?php

declare(strict_types=1);

use Capell\Blog\Models\Article;
use Capell\Blog\Support\Creator\BlogCreator;
use Capell\Core\Data\PageTypeData;
use Capell\Core\Facades\CapellCore;
use Capell\Core\Models\Blueprint;
use Capell\LayoutBuilder\Filament\Resources\Widgets\Pages\EditWidget;
use Capell\LayoutBuilder\Models\Widget;
use Capell\Tests\Support\Concerns\CreatesAdminUser;

use function Pest\Livewire\livewire;

uses(CreatesAdminUser::class)
    ->group('widget');

beforeEach(function (): void {
    test()->actingAsAdmin();
});

test('can edit related widget', function (): void {
    $typeCreator = new BlogCreator;
    $widget = $typeCreator->relatedArticlesWidget();

    $newData = Widget::factory()->make();

    Blueprint::factory()->page()->state(['key' => 'home'])->create();
    CapellCore::registerPageType(new PageTypeData(
        name: 'article',
        model: Article::class,
        label: fn (): string => __('capell-blog::generic.article'),
    ));

    livewire(EditWidget::class, [
        'record' => $widget->getRouteKey(),
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

    expect($widget->refresh())
        ->name->toBe($newData->name)
        ->key->toBe($newData->key);
});
