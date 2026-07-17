<?php

declare(strict_types=1);

use Capell\Blog\Actions\ResolveArticleCreateSiteAction;
use Capell\Core\Models\Site;
use Capell\Tests\Fixtures\Models\User;
use Capell\Tests\Support\Concerns\CreatesAdminUser;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

uses(CreatesAdminUser::class);

it('rejects article site resolution without an authenticated actor', function (): void {
    expect(fn (): Site => ResolveArticleCreateSiteAction::run(null))
        ->toThrow(AuthorizationException::class, __('capell-admin::message.site_not_accessible'));
});

it('prefers the default site for a global administrator', function (): void {
    test()->actingAsAdmin();
    Site::factory()->create(['default' => false]);
    $defaultSite = Site::factory()->default()->create();

    expect(ResolveArticleCreateSiteAction::run(null)->is($defaultSite))->toBeTrue();
});

it('rejects malformed site identifiers instead of coercing them', function (): void {
    test()->actingAsAdmin();
    $site = Site::factory()->create();

    expect(fn (): Site => ResolveArticleCreateSiteAction::run($site->getKey() . '.5'))
        ->toThrow(AuthorizationException::class, __('capell-admin::message.site_not_accessible'));
});

it('resolves only a requested site assigned to the actor', function (): void {
    $assignedSite = Site::factory()->create();
    $unassignedSite = Site::factory()->create();
    $role = capell_test_instance(Role::findOrCreate('blog-site-editor', 'web'), Role::class);
    $actor = capell_test_instance(test()->createUser(), User::class);
    $actor->assignRoleForSite($assignedSite, $role);
    DB::table('model_has_roles')
        ->where('role_id', $role->getKey())
        ->where('model_type', $actor->getMorphClass())
        ->where('model_id', $actor->getKey())
        ->update(['team_id' => $assignedSite->getKey()]);
    $registrar = resolve(PermissionRegistrar::class);
    $previousSiteId = $registrar->getPermissionsTeamId();
    $registrar->setPermissionsTeamId($assignedSite->getKey());
    test()->actingAs($actor);

    try {
        expect($actor->getAssignedSiteIds()->all())
            ->toContain($assignedSite->getKey())
            ->and(ResolveArticleCreateSiteAction::run($assignedSite->getKey())->is($assignedSite))
            ->toBeTrue()
            ->and(fn (): Site => ResolveArticleCreateSiteAction::run($unassignedSite->getKey()))
            ->toThrow(AuthorizationException::class, __('capell-admin::message.site_not_accessible'));
    } finally {
        $registrar->setPermissionsTeamId($previousSiteId);
    }
});

it('falls back to an assigned site when the create form has no site value', function (): void {
    $assignedSite = Site::factory()->create(['default' => false]);
    Site::factory()->default()->create();
    $role = capell_test_instance(Role::findOrCreate('blog-site-editor', 'web'), Role::class);
    $actor = capell_test_instance(test()->createUser(), User::class);
    $actor->assignRoleForSite($assignedSite, $role);
    DB::table('model_has_roles')
        ->where('role_id', $role->getKey())
        ->where('model_type', $actor->getMorphClass())
        ->where('model_id', $actor->getKey())
        ->update(['team_id' => $assignedSite->getKey()]);
    $registrar = resolve(PermissionRegistrar::class);
    $previousSiteId = $registrar->getPermissionsTeamId();
    $registrar->setPermissionsTeamId($assignedSite->getKey());
    test()->actingAs($actor);

    try {
        expect($actor->getAssignedSiteIds()->all())
            ->toContain($assignedSite->getKey())
            ->and(ResolveArticleCreateSiteAction::run(null)->is($assignedSite))
            ->toBeTrue();
    } finally {
        $registrar->setPermissionsTeamId($previousSiteId);
    }
});
