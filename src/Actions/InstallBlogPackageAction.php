<?php

declare(strict_types=1);

namespace Capell\Blog\Actions;

use Capell\Admin\Actions\AssignPermissionsToRole;
use Capell\Blog\Enums\ResourceEnum;
use Capell\Blog\Support\BlogModelRegistrar;
use Capell\Core\Actions\Install\PublishPackageMigrationsAction;
use Capell\Core\Actions\Install\RunArtisanCommandAction;
use Capell\Core\Actions\Install\RunMigrationsAction;
use Capell\Core\Contracts\PackageLifecycleAction;
use Capell\Core\Contracts\ProgressReporter;
use Capell\Core\Data\PackageData;
use Capell\Core\Support\Install\NullProgressReporter;
use Filament\Facades\Filament;
use Illuminate\Support\Collection;
use Lorisleiva\Actions\Concerns\AsObject;

final class InstallBlogPackageAction implements PackageLifecycleAction
{
    use AsObject;

    public function handle(PackageData $package, array $arguments = [], ?ProgressReporter $reporter = null): void
    {
        $reporter ??= new NullProgressReporter;

        BlogModelRegistrar::register();

        Filament::getDefaultPanel()
            ->resources(array_map(fn (ResourceEnum $resourceEnum): string => $resourceEnum->value, ResourceEnum::cases()));

        AssignPermissionsToRole::run(resources: array_map(fn (ResourceEnum $resourceEnum): string => $resourceEnum->value, ResourceEnum::cases()));

        PublishPackageMigrationsAction::run(new Collection([$package->name => $package]), $reporter, true, false);
        RunMigrationsAction::run($reporter);

        if (! app()->runningUnitTests()) {
            RunArtisanCommandAction::run('filament:assets', [], $reporter);
        }

        $reporter->report('Capell Blog installed successfully.');
    }
}
