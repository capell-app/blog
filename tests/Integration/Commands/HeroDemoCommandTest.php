<?php

declare(strict_types=1);

use Capell\Core\Models\Blueprint;
use Capell\Core\Models\Layout;
use Capell\Core\Models\Page;
use Capell\Core\Models\Site;

describe('capell:hero-demo command', function (): void {
    it('creates blog hero demo content for selected sites', function (): void {
        $site = Site::factory()->withTranslations()->create(['name' => 'Hero Demo Site']);
        $layout = Layout::factory()->create();
        $blogType = Blueprint::factory()->page()->create(['key' => 'blog']);

        Page::factory()
            ->site($site)
            ->layout($layout)
            ->type($blogType)
            ->withTranslations()
            ->create(['name' => 'Blog']);

        $this->artisan('capell:hero-demo', [
            '--sites' => 'Hero Demo Site',
        ])
            ->expectsOutput('[1/1] Creating blog hero demo content for "Hero Demo Site".')
            ->expectsOutput('Demo hero content has been successfully created for site: Hero Demo Site')
            ->assertSuccessful();
    });
});
