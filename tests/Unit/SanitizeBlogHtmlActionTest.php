<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Blade;

it('sanitizes hostile no-results html at the render boundary', function (): void {
    $html = Blade::render(
        '@safeBlogHtml($noResultsText)',
        ['noResultsText' => '<script>alert(1)</script><p onclick="alert(2)">Nothing found.</p>'],
    );

    expect($html)->toContain('<p>Nothing found.</p>')
        ->not->toContain('<script', 'onclick=');
});
