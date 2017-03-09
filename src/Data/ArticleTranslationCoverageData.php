<?php

declare(strict_types=1);

namespace Capell\Blog\Data;

use Capell\Blog\Models\Article;
use Capell\Core\Models\Language;
use Capell\Core\Models\Translation;
use LogicException;
use Spatie\LaravelData\Data;

final class ArticleTranslationCoverageData extends Data
{
    /** @param list<string> $missingLanguages */
    public function __construct(
        public readonly string $languages,
        public readonly array $missingLanguages,
    ) {}

    public static function fromArticle(Article $article, ?int $languageId = null): self
    {
        $site = $article->site ?? throw new LogicException('Article translation coverage requires a site.');
        $languages = $site->languages;
        if ($languageId !== null) {
            $languages = $languages->where('id', $languageId);
        }

        $missing = $languages->filter(function (Language $language) use ($article): bool {
            $translation = $article->translations->firstWhere('language_id', $language->getKey());

            return ! $translation instanceof Translation || blank($translation->title)
                || blank(data_get($translation->meta, 'slug'));
        });

        return new self(
            $languages->pluck('name')->implode(', '),
            array_values($missing->map(fn (Language $language): string => $language->name)->all()),
        );
    }
}
