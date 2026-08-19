<?php

declare(strict_types=1);

use Capell\Blog\Actions\ResolveArticleExcerptAction;
use Capell\Core\Models\Translation;

function blogExcerptTranslation(?string $summary, ?string $content): Translation
{
    $translation = new Translation;
    $translation->forceFill([
        'meta' => $summary === null ? [] : ['summary' => $summary],
        'content' => $content,
    ]);

    return $translation;
}

it('prefers an authored summary', function (): void {
    $excerpt = ResolveArticleExcerptAction::run(
        blogExcerptTranslation('Authored summary.', '<p>Body content.</p>'),
    );

    expect($excerpt)->toBe('Authored summary.');
});

it('falls back to the body content when no summary was authored', function (): void {
    $excerpt = ResolveArticleExcerptAction::run(
        blogExcerptTranslation(null, '<p>Body <strong>content</strong> here.</p>'),
    );

    expect($excerpt)->toBe('Body content here.');
});

it('decodes entities and collapses whitespace', function (): void {
    $excerpt = ResolveArticleExcerptAction::run(
        blogExcerptTranslation(null, "<p>Tea &amp;   coffee</p>\n\n<p>both work</p>"),
    );

    expect($excerpt)->toBe('Tea & coffee both work');
});

it('truncates a long content fallback on a word boundary', function (): void {
    $excerpt = ResolveArticleExcerptAction::run(
        blogExcerptTranslation(null, '<p>' . str_repeat('word ', 100) . '</p>'),
        limit: 20,
    );

    expect($excerpt)->toBe('word word word...')
        ->and(mb_strlen($excerpt))->toBeLessThanOrEqual(23);
});

it('returns an empty string when there is nothing to excerpt', function (): void {
    expect(ResolveArticleExcerptAction::run(null))->toBe('')
        ->and(ResolveArticleExcerptAction::run(blogExcerptTranslation(null, '<p> </p>')))->toBe('');
});
