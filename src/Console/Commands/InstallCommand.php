<?php

declare(strict_types=1);

namespace Capell\Blog\Console\Commands;

use Capell\Blog\Actions\InstallBlogPackageAction;
use Capell\Core\Facades\CapellCore;
use Capell\Core\Support\Install\ConsoleProgressReporter;
use Illuminate\Console\Command;

class InstallCommand extends Command
{
    protected $description = 'Install blog package';

    protected $signature = 'capell:blog-install';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        InstallBlogPackageAction::run(CapellCore::getPackage('capell-app/blog'), [], new ConsoleProgressReporter($this));

        $this->newLine();
        $this->info('Capell Blog installed successfully.');

        return self::SUCCESS;
    }
}
