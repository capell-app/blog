<?php

declare(strict_types=1);

use Capell\Admin\Filament\Pages\PageViewsPage;
use Capell\Blog\Models\Article;
use Capell\Core\Models\AccessLog;
use Capell\Tests\Support\Concerns\CreatesAdminUser;

use function Pest\Livewire\livewire;

use Spatie\Permission\Models\Permission;

uses(CreatesAdminUser::class)
    ->group('access-logs');

test('can render articles in access logs', function (): void {
    Permission::create(['name' => 'View:PageViewsPage', 'guard_name' => 'web']);
    test()->actingAsAdmin();
    auth()->user()->givePermissionTo('View:PageViewsPage');

    $article = Article::factory()->create();

    AccessLog::factory()->create();

    AccessLog::factory()->page($article)->create();

    livewire(PageViewsPage::class)
        ->assertSuccessful()
        ->assertCountTableRecords(2);
});
