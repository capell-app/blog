<?php

declare(strict_types=1);

namespace Capell\Blog\Tests\Fixtures;

use Capell\Core\Contracts\ProgressReporter;

final readonly class NoopRunMigrationsAction
{
    public function handle(
        ProgressReporter $reporter,
        bool $includeSettings = true,
        bool $includeSchema = true,
    ): void {}
}
