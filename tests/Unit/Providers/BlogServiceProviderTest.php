<?php

declare(strict_types=1);

use Livewire\Blaze\Config as BlazeConfig;

test('blog widget components are registered for blaze compilation with nested components', function (): void {
    $blazeConfig = resolve(BlazeConfig::class);

    expect($blazeConfig->shouldCompile(__DIR__ . '/../../../resources/views/components/widget/page/article.blade.php'))->toBeTrue()
        ->and($blazeConfig->shouldCompile(__DIR__ . '/../../../resources/views/components/page/published-date.blade.php'))->toBeTrue();
});
