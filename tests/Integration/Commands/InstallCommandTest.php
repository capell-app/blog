<?php

declare(strict_types=1);

use Capell\Blog\Tests\Fixtures\NoopRunMigrationsAction;
use Capell\Core\Actions\Install\RunMigrationsAction;
use Capell\Core\Support\Migration\MigrationFilesystemInterface;
use Capell\Tests\Fixtures\FakeMigrationFileManager;
use Illuminate\Console\Command;

it('runs blog install command successfully without publishing files', function (): void {
    $fakeFileManager = new FakeMigrationFileManager([
        'fileExists' => [],
        'isDir' => [],
    ]);

    app()->instance(MigrationFilesystemInterface::class, $fakeFileManager);
    app()->instance(RunMigrationsAction::class, new NoopRunMigrationsAction);

    $this->artisan('capell:blog-install')
        ->doesntExpectOutput('Publishing migrations')
        ->doesntExpectOutput('Migrating')
        ->doesntExpectOutput('Building assets')
        ->assertExitCode(Command::SUCCESS);

    // Assert no migration files were actually published
    expect($fakeFileManager->calls)
        ->not()->toContain(fn (array $call): bool => $call[0] === 'copy')
        ->toBeArray();

    // Assert no migration files were copied or directories created by the publish command internals
    expect(collect($fakeFileManager->calls)->contains(
        fn (array $call): bool => in_array($call[0], ['copy', 'makeDir'], true),
    ))->toBeFalse();
});
