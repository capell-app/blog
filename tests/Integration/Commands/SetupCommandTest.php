<?php

declare(strict_types=1);

use Capell\Blog\Actions\SeedBlogPublishingSurfaceAction;
use Illuminate\Console\Command;

it('runs blog setup through the publishing surface seed action', function (): void {
    SeedBlogPublishingSurfaceAction::shouldRun()->once();

    $this->artisan('capell:blog-setup')
        ->expectsOutput('Capell Blog setup successfully.')
        ->assertExitCode(Command::SUCCESS);
});
