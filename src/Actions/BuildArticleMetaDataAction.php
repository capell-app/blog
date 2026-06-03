<?php

declare(strict_types=1);

namespace Capell\Blog\Actions;

use Capell\Blog\Data\ArticleMetaData;
use Capell\Blog\Data\BlogTagLinkData;
use Capell\Blog\Support\Loader\TagLoader;
use Capell\Core\Contracts\Pageable;
use Capell\Core\Models\Language;
use Capell\Core\Models\Page;
use Capell\Core\Models\Site;
use Capell\Core\Models\Translation;
use Capell\Frontend\Contracts\RenderedModelTracker;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Log;
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
        if ($withAuthor && ! $author instanceof Model && $page instanceof Model) {
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

        if ($tags->isNotEmpty() && ! $tagPage instanceof Page) {
            Log::warning('Blog article tags could not be linked because no tag results page exists.', [
                'site_id' => $site->getKey(),
                'language_id' => $language->getKey(),
                'page_type' => $page instanceof Model ? $page::class : $page::class,
                'page_id' => $page instanceof Model ? $page->getKey() : null,
            ]);
        }

        $translation = $page instanceof Model && $page->relationLoaded('translation')
            ? $page->getRelation('translation')
            : null;
        $content = $translation instanceof Translation && is_string($translation->content)
            ? $translation->content
            : null;
        $publishedAt = $page instanceof Model ? $page->getAttribute('visible_from') ?: $page->getAttribute('created_at') : null;
        $modifiedAt = $page instanceof Model ? $page->getAttribute('updated_at') : null;

        return new ArticleMetaData(
            tags: $tags,
            tagLinks: $tagPage instanceof Page ? BlogTagLinkData::collectionFromTags($tags, $tagPage, $language) : [],
            tagPage: $tagPage,
            language: $language,
            author: $author,
            withAuthor: $withAuthor,
            authorName: $author instanceof Model && is_string($author->getAttribute('name')) ? $author->getAttribute('name') : null,
            publishedAt: $publishedAt instanceof CarbonInterface ? $publishedAt : null,
            modifiedAt: $modifiedAt instanceof CarbonInterface ? $modifiedAt : null,
            readingTimeMinutes: $this->readingTimeMinutes($content),
        );
    }

    private function readingTimeMinutes(?string $content): ?int
    {
        if ($content === null || trim($content) === '') {
            return null;
        }

        $wordCount = str_word_count(strip_tags($content));

        if ($wordCount === 0) {
            return null;
        }

        return max(1, (int) ceil($wordCount / 200));
    }
}
