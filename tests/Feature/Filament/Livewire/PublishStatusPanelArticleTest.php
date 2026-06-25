<?php

declare(strict_types=1);

use Capell\Admin\Filament\Livewire\PublishStatusPanel;
use Capell\Blog\Models\Article;
use Capell\Tests\Support\Concerns\CreatesAdminUser;
use Livewire\Features\SupportTesting\Testable;
use Livewire\Livewire;

uses(CreatesAdminUser::class)->group('page', 'article');

beforeEach(function (): void {
    test()->actingAsAdmin();
});

function articlePanel(Article $article): Testable
{
    return Livewire::test(PublishStatusPanel::class, [
        'recordClass' => Article::class,
        'recordId' => $article->getKey(),
    ]);
}

it('shows the publish controls for a live article', function (): void {
    $article = Article::factory()->create([
        'visible_from' => now()->subDay(),
        'visible_until' => null,
    ]);

    articlePanel($article)
        ->assertOk()
        ->assertActionVisible('unpublish');
});

it('does not show a status toggle for a non-statusable article', function (): void {
    $article = Article::factory()->create([
        'visible_from' => now()->subDay(),
        'visible_until' => null,
    ]);

    articlePanel($article)
        ->assertOk()
        ->assertActionHidden('toggleStatus');
});

it('publishes a draft article immediately via the panel', function (): void {
    $article = Article::factory()->create([
        'visible_from' => now()->addYears(100),
        'visible_until' => null,
    ]);

    articlePanel($article)->callAction('publishNow');

    expect($article->fresh()->isPending())->toBeFalse();
});

it('unpublishes a live article via the panel', function (): void {
    $article = Article::factory()->create([
        'visible_from' => now()->subDay(),
        'visible_until' => null,
    ]);

    articlePanel($article)->callAction('unpublish');

    expect($article->fresh()->isExpired())->toBeTrue();
});
