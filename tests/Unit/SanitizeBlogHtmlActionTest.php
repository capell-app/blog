<?php

declare(strict_types=1);

it('sanitizes hostile no-results html in the results slot render boundary', function (): void {
    $html = view('capell-blog::livewire.page.results-slot', [
        'results' => collect(),
        'noResultsText' => '<script>alert(1)</script><p onclick="alert(2)">Nothing found.</p>',
    ])->render();

    expect($html)->toContain('data-blog-results')
        ->and($html)->toContain('<p>Nothing found.</p>')
        ->not->toContain('<script', 'onclick=');
});
