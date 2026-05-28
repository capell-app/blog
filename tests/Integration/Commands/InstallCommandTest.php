<?php

declare(strict_types=1);

use Capell\Core\Support\Migration\MigrationFilesystemInterface;
use Capell\Tests\Fixtures\FakeMigrationFileManager;
use Illuminate\Console\Command;
use Illuminate\Contracts\Events\Dispatcher;
use Illuminate\Database\Console\Migrations\MigrateCommand;
use Illuminate\Database\Migrations\Migrator;

afterEach(function (): void {
    Mockery::close();
});

it('runs blog install command successfully without publishing files', function (): void {
    $fakeFileManager = new FakeMigrationFileManager([
        'fileExists' => [],
        'isDir' => [],
    ]);

    // Ensure migrate command is a no-op
    $fakeMigrationAssistant = Mockery::mock(Migrator::class);
    $fakeDispatcher = Mockery::mock(Dispatcher::class);
    test()->instance(
        MigrateCommand::class,
        Mockery::mock(new MigrateCommand($fakeMigrationAssistant, $fakeDispatcher))
            ->makePartial()
            ->shouldReceive('run')->once()->andReturn(0)->getMock(),
    );

    // If Filament AssetsCommand is available, stub it as a no-op
    if (class_exists('Filament\\Commands\\AssetsCommand')) {
        test()->instance(
            'Filament\\Commands\\AssetsCommand',
            Mockery::mock('Filament\\Commands\\AssetsCommand', [])->makePartial()
                ->shouldReceive('run')->once()->andReturn(0)->getMock(),
        );
    }

    app()->instance(MigrationFilesystemInterface::class, $fakeFileManager);

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
