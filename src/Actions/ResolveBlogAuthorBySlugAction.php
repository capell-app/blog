<?php

declare(strict_types=1);

namespace Capell\Blog\Actions;

use Capell\Blog\Data\BlogAuthorData;
use Capell\Blog\Models\Article;
use Capell\Core\Models\Language;
use Capell\Core\Models\Site;
use Illuminate\Database\Eloquent\Model;
use Lorisleiva\Actions\Concerns\AsFake;
use Lorisleiva\Actions\Concerns\AsObject;

/**
 * Resolve a public author archive slug to the user that authored the articles.
 *
 * Core owns the users table and it carries neither a slug nor a username, and
 * neither the primary key (enumerable) nor the email address (PII) may appear
 * in a public URL. The public slug is therefore derived from the author display
 * name at request time and matched only against users who actually have
 * published articles on the requested site and language. When two such authors
 * slugify to the same value the lowest user id wins, so the mapping stays
 * deterministic.
 *
 * @method static BlogAuthorData|null run(string $slug, Site $site, Language $language)
 */
class ResolveBlogAuthorBySlugAction
{
    use AsFake;
    use AsObject;

    public function handle(string $slug, Site $site, Language $language): ?BlogAuthorData
    {
        $normalizedSlug = BlogAuthorData::slugForName($slug);

        if ($normalizedSlug === '') {
            return null;
        }

        $article = new Article;
        $createdByColumn = $article->qualifyColumn($article->getCreatedByColumn());

        $creatorIds = Article::query()
            ->published()
            ->where($article->qualifyColumn('site_id'), $site->getKey())
            ->whereRelation('translation', 'language_id', $language->getKey())
            ->whereNotNull($createdByColumn)
            ->distinct()
            ->pluck($createdByColumn)
            ->all();

        if ($creatorIds === []) {
            return null;
        }

        $userClass = $article->getUserClass();

        if ($userClass === '' || ! class_exists($userClass)) {
            return null;
        }

        /** @var class-string<Model> $userClass */
        $candidates = $userClass::query()
            ->whereKey($creatorIds)
            ->orderBy((new $userClass)->getKeyName())
            ->get();

        foreach ($candidates as $candidate) {
            $name = $candidate->getAttribute('name');

            if (! is_string($name)) {
                continue;
            }

            if (BlogAuthorData::slugForName($name) !== $normalizedSlug) {
                continue;
            }

            $userId = $candidate->getKey();

            if (! is_numeric($userId)) {
                continue;
            }

            return new BlogAuthorData(
                userId: (int) $userId,
                slug: $normalizedSlug,
                name: trim($name),
            );
        }

        return null;
    }
}
