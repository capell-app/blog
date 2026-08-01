<?php

declare(strict_types=1);

namespace Capell\Blog\Data;

use Capell\Core\Models\Language;
use Capell\Core\Models\Site;
use Illuminate\Support\Collection;
use InvalidArgumentException;
use Spatie\LaravelData\Data;

final class BlogPublishingSurfaceRequestData extends Data
{
    /**
     * @var Collection<int, Language>
     */
    public readonly Collection $languages;

    /**
     * @param  Collection<int, Language>|null  $languages
     */
    public function __construct(
        public readonly Site $site,
        ?Collection $languages = null,
        public readonly bool $createWidgets = true,
    ) {
        $resolvedLanguages = $languages ?? $site->getAllLanguages();

        if ($resolvedLanguages->contains(
            static fn (mixed $language): bool => ! $language instanceof Language,
        )) {
            throw new InvalidArgumentException('Blog publishing surface languages must be Language models.');
        }

        /** @var Collection<int, Language> $uniqueLanguages */
        $uniqueLanguages = $resolvedLanguages
            ->unique(static fn (Language $language): mixed => $language->getKey())
            ->values();

        $this->languages = $uniqueLanguages;
    }

    /**
     * Compatibility for legacy creator methods that have not yet narrowed
     * their generic Collection value type.
     *
     * @return Collection<array-key, mixed>
     */
    public function languagesForLegacyCreator(): Collection
    {
        $legacyLanguages = new Collection;

        foreach ($this->languages as $language) {
            $legacyLanguages->push($language);
        }

        return $legacyLanguages;
    }
}
