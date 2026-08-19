<?php

declare(strict_types=1);

namespace Capell\Blog\Actions;

use Capell\Blog\Data\BlogPublishingSurfaceData;
use Capell\Blog\Data\BlogPublishingSurfaceRequestData;
use Capell\Blog\Data\BlogPublishingSurfaceResultData;
use Capell\Blog\Enums\BlogPageTypeEnum;
use Capell\Blog\Support\Creator\BlogCreator;
use Capell\Core\Actions\GetOrCreateResultsLayoutAction;
use Capell\Core\Enums\PageTypeEnum;
use Capell\Core\Models\Blueprint;
use Capell\Core\Support\Creator\BlueprintCreator;
use LogicException;
use Lorisleiva\Actions\Concerns\AsFake;
use Lorisleiva\Actions\Concerns\AsObject;

/**
 * @internal
 *
 * @method static BlogPublishingSurfaceResultData run(BlogPublishingSurfaceRequestData $request)
 */
class ProvisionBlogPublishingPagesAction
{
    use AsFake;
    use AsObject;

    public function handle(BlogPublishingSurfaceRequestData $request): BlogPublishingSurfaceResultData
    {
        $blogCreator = resolve(BlogCreator::class);

        $blogPage = $blogCreator->createBlogPage(
            $request->site,
            type: $this->getPageType($blogCreator, BlogPageTypeEnum::Blog->value),
            layout: $blogCreator->createBlogPageLayout(),
            languages: $request->languages,
        );

        $archivesPage = $blogCreator->createArchivesPage(
            $blogPage,
            type: $this->getPageType($blogCreator, PageTypeEnum::System->value),
            layout: $blogCreator->createArchivesLayout(),
            languages: $request->languages,
        );

        $archivePage = $blogCreator->createArchivePage(
            $archivesPage,
            type: $this->getPageType($blogCreator, BlogPageTypeEnum::Archive->value),
            layout: GetOrCreateResultsLayoutAction::run(),
            languages: $request->languages,
        );

        $tagsPage = $blogCreator->createTagsPage(
            $request->site,
            $blogPage,
            languages: $request->languages,
            type: $this->getPageType($blogCreator, PageTypeEnum::System->value),
            layout: $blogCreator->createTagsLayout(),
        );

        $tagPage = $blogCreator->createTagPage(
            $request->site,
            $tagsPage,
            languages: $request->languages,
            type: $this->getPageType($blogCreator, BlogPageTypeEnum::Tag->value),
            layout: $blogCreator->createTagResultsLayout(),
        );

        $authorPage = $blogCreator->createAuthorPage(
            $request->site,
            $blogPage,
            languages: $request->languages,
            type: $this->getPageType($blogCreator, BlogPageTypeEnum::Author->value),
            layout: $blogCreator->createAuthorResultsLayout(),
        );

        $surface = new BlogPublishingSurfaceData(
            blogPage: $blogPage,
            archivesPage: $archivesPage,
            archivePage: $archivePage,
            tagsPage: $tagsPage,
            tagPage: $tagPage,
            authorPage: $authorPage,
        );

        foreach ($surface->pages() as $page) {
            $page->loadMissing('type');
        }

        return $surface;
    }

    private function getPageType(BlogCreator $blogCreator, string $key): Blueprint
    {
        $type = Blueprint::query()->where('key', $key)->pageType()->first();

        if ($type instanceof Blueprint) {
            return $type;
        }

        $createdType = match ($key) {
            BlogPageTypeEnum::Archive->value => $blogCreator->createArchivePageType(),
            BlogPageTypeEnum::Author->value => $blogCreator->createAuthorPageType(),
            BlogPageTypeEnum::Blog->value => $blogCreator->createBlogPageType(),
            BlogPageTypeEnum::Tag->value => $blogCreator->createTagPageType(),
            PageTypeEnum::System->value => resolve(BlueprintCreator::class)->systemPageType(),
            default => resolve(BlueprintCreator::class)->createPageType($key),
        };

        if ($createdType instanceof Blueprint) {
            return $createdType;
        }

        throw new LogicException('Expected page type creator to return a Blueprint model.');
    }
}
