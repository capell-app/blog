<?php

declare(strict_types=1);

use BezhanSalleh\FilamentShield\Facades\FilamentShield;
use BezhanSalleh\FilamentShield\Support\Utils;
use Capell\Blog\Filament\Resources\Articles\ArticleResource;
use Capell\Blog\Models\Article;
use Capell\Blog\Policies\ArticlePolicy;
use Capell\Tests\Fixtures\Models\User;
use Filament\Facades\Filament;
use Illuminate\Auth\Access\Gate as AuthGate;
use Illuminate\Support\Facades\Gate;
use Spatie\Permission\Models\Permission;

it('registers the article policy for strict resource authorization', function (): void {
    $actor = User::factory()->create();
    test()->actingAs($actor);

    expect(Filament::isAuthorizationStrict())->toBeTrue()
        ->and(Gate::getPolicyFor(Article::class))->toBeInstanceOf(ArticlePolicy::class)
        ->and(ArticleResource::canViewAny())->toBeFalse();
});

it('resolves the article policy before runtime provider registration without host policy discovery', function (): void {
    $actor = User::factory()->create();
    test()->actingAs($actor);
    $gate = new AuthGate(app(), fn (): User => $actor);
    $gate->guessPolicyNamesUsing(static fn (): array => []);
    Gate::swap($gate);

    expect(Gate::getPolicyFor(Article::class))->toBeInstanceOf(ArticlePolicy::class)
        ->and(ArticleResource::canViewAny())->toBeFalse();
});

it('requires the configured article permission for each strict resource ability', function (string $ability, string $affix, bool $recordAbility): void {
    $actor = User::factory()->create();
    $article = new Article;
    $arguments = $recordAbility ? [$article] : [Article::class];
    test()->actingAs($actor);

    expect(ArticleResource::getAuthorizationResponse($ability, $recordAbility ? $article : null)->denied())->toBeTrue();

    $permissions = Utils::getConfig()->permissions;
    $permission = FilamentShield::defaultPermissionKeyBuilder(
        affix: $affix,
        separator: $permissions->separator,
        subject: 'Article',
        case: $permissions->case,
    );
    Permission::findOrCreate($permission, 'web');
    $actor->givePermissionTo($permission);

    expect(Gate::forUser($actor)->allows($ability, $arguments))->toBeTrue()
        ->and(ArticleResource::getAuthorizationResponse($ability, $recordAbility ? $article : null)->allowed())->toBeTrue();
})->with([
    'viewAny' => ['viewAny', 'view_any', false],
    'view' => ['view', 'view', true],
    'create' => ['create', 'create', false],
    'update' => ['update', 'update', true],
    'delete' => ['delete', 'delete', true],
    'deleteAny' => ['deleteAny', 'delete_any', false],
    'restore' => ['restore', 'restore', true],
    'restoreAny' => ['restoreAny', 'restore_any', false],
    'forceDelete' => ['forceDelete', 'force_delete', true],
    'forceDeleteAny' => ['forceDeleteAny', 'force_delete_any', false],
    'replicate' => ['replicate', 'replicate', true],
    'reorder' => ['reorder', 'reorder', false],
]);
