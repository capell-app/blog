<?php

declare(strict_types=1);

namespace Capell\Blog\Actions;

use Capell\Blog\Data\ArticleMetaData;
use Capell\Blog\Support\Loader\TagLoader;
use Capell\Core\Contracts\Pageable;
use Capell\Core\Models\Language;
use Capell\Core\Models\Page;
use Capell\Core\Models\Site;
use Capell\Frontend\Contracts\RenderedModelTracker;
use Exception;
use Illuminate\Database\Eloquent\Model;
use Lorisleiva\Actions\Concerns\AsObject;

final class BuildArticleMetaDataAction
{
    use AsObject;

    public function handle(
        Pageable $page,
        Site $site,
        Language $language,
        bool $withAuthor = false,
        ?Model $author = null,
    ): ArticleMetaData {
        if ($withAuthor && ! $author instanceof Model && $page instanceof Model && method_exists($page, 'creator')) {
            $creator = $page->relationLoaded('creator')
                ? $page->getRelation('creator')
                : $page->creator()->first();

            if ($creator instanceof Model) {
                $author = $creator;
                $page->setRelation('creator', $creator);
            }
        }

        if ($author instanceof Model) {
            if (method_exists($author, 'profileImage')) {
                $author->loadMissing('profileImage');
            }

            resolve(RenderedModelTracker::class)->track($author);
        }

        $tags = TagLoader::getPageTags($page);
        $tagPage = $tags->isNotEmpty()
            ? TagLoader::getTagResultsPage($site, $language)
            : null;

        throw_if(
            $tags->isNotEmpty() && ! $tagPage instanceof Page,
            Exception::class,
            'Tag results page not found for the current site ' . $site->id . ' and language ' . $language->id,
        );

        return new ArticleMetaData(
            tags: $tags,
            tagPage: $tagPage,
            author: $author,
            withAuthor: $withAuthor,
        );
    }
}
