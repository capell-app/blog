<?php

declare(strict_types=1);

use Illuminate\Console\Command;

use function Pest\Laravel\artisan;

describe('capell:blog-demo command', function (): void {
    it('runs the existing article and hero demo commands', function (): void {
        artisan('capell:blog-demo')
            ->expectsOutput('No sites found. Skipping.')
            ->expectsOutput('Unable to find any selected sites.')
            ->assertExitCode(Command::FAILURE);
    });
});
