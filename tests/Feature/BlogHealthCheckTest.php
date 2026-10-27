<?php

declare(strict_types=1);

use Capell\Blog\Actions\SeedBlogPublishingSurfaceAction;
use Capell\Blog\Health\BlogHealthCheck;
use Capell\Core\Data\Diagnostics\DoctorCheckResultData;

it('reports compatible capell api version', function (): void {
    expect(BlogHealthCheck::compatibleCapellApiVersion())->toBe('^1.0');
});

it('runs the declared blog diagnostics', function (): void {
    SeedBlogPublishingSurfaceAction::run();

    $checks = BlogHealthCheck::runDiagnostics();

    expect($checks)->toHaveCount(4)
        ->and($checks->every(fn (DoctorCheckResultData $check): bool => $check->passed))->toBeTrue()
        ->and($checks->pluck('id')->all())->toBe([
            'legacy.blog.publishing.surface',
            'legacy.blog.cache.invalidation',
            'legacy.blog.author.and.related.rendering',
            'legacy.blog.sitemap.and.static.export',
        ])
        ->and(BlogHealthCheck::passed())->toBeTrue();
});
