<?php

declare(strict_types=1);

namespace Capell\Blog\Actions;

use Capell\Core\Models\Translation;
use Illuminate\Support\Str;
use Lorisleiva\Actions\Concerns\AsFake;
use Lorisleiva\Actions\Concerns\AsObject;

/**
 * Resolves the short excerpt used for feed descriptions.
 *
 * Core's Translation::summary accessor already falls back to the body content
 * when no summary was authored, but that fallback keeps up to 200 words. Feed
 * descriptions need a short, single-line, entity-safe excerpt instead.
 *
 * @method static string run(?Translation $translation, int $limit = 200)
 */
final class ResolveArticleExcerptAction
{
    use AsFake;
    use AsObject;

    public function handle(?Translation $translation, int $limit = 200): string
    {
        if (! $translation instanceof Translation) {
            return '';
        }

        $summary = $translation->summary;
        $text = $this->normalise(is_string($summary) ? $summary : '');

        if ($text === '') {
            return '';
        }

        return Str::limit($text, max(1, $limit), preserveWords: true);
    }

    private function normalise(string $value): string
    {
        $text = html_entity_decode(strip_tags($value), ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $text = preg_replace('/\s+/u', ' ', $text) ?? $text;

        return trim($text);
    }
}
