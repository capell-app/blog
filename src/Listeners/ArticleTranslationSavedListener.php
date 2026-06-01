<?php

declare(strict_types=1);

namespace Capell\Blog\Listeners;

use Capell\Blog\Models\Article;
use Capell\Blog\Support\Loader\BlogLoader;
use Capell\Core\Actions\UpdatePageUrlAction;
use Capell\Core\Models\Language;
use Capell\Core\Models\Site;
use Capell\Core\Models\Translation;
use Illuminate\Database\Eloquent\Relations\Relation;

final class ArticleTranslationSavedListener
{
    public function __invoke(Translation $translation): void
    {
        $articleMorphAlias = Relation::getMorphAlias(Article::class);

        if ($articleMorphAlias === Article::class || $translation->translatable_type !== $articleMorphAlias) {
            return;
        }

        /** @var Article|null $article */
        $article = $translation->translatable()->first();
        $language = $translation->language()->first();

        if (! $article instanceof Article || ! $language instanceof Language) {
            return;
        }

        $article->loadMissing('site');
        $site = $article->site;

        if (! $site instanceof Site) {
            return;
        }

        $url = BlogLoader::getBlogPageUrl($site, $language, fullUrl: false);

        UpdatePageUrlAction::run($site, $translation, $url);
    }
}
