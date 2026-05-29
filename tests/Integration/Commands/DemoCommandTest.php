<?php

declare(strict_types=1);

use Illuminate\Console\Command;

describe('capell:blog-demo command', function (): void {
    it('runs the existing article and hero demo commands', function (): void {
        $this->artisan('capell:blog-demo')
            ->expectsOutput('Starting blog demo content.')
            ->expectsOutput('No sites found. Skipping.')
            ->expectsOutput('Starting blog hero demo content.')
            ->expectsOutput('Unable to find any selected sites.')
            ->assertExitCode(Command::FAILURE);
    });
});
