<?php

declare(strict_types=1);

namespace Capell\Blog\Actions;

use Capell\Blog\Data\BlogPublishingSurfaceData;
use Capell\Blog\Enums\BlogPageTypeEnum;
use Capell\Blog\Support\Creator\BlogCreator;
use Capell\Core\Actions\GetOrCreateResultsLayoutAction;
use Capell\Core\Enums\PageTypeEnum;
use Capell\Core\Models\Blueprint;
use Capell\Core\Models\Layout;
use Capell\Core\Models\Site;
use Capell\Core\Support\Creator\BlueprintCreator;
use Capell\LayoutBuilder\Support\Creator\ElementCreator;
use Capell\LayoutBuilder\Support\Creator\TypeCreator as LayoutTypeCreator;
use Capell\Navigation\Enums\NavigationHandle;
use Illuminate\Support\Collection;
use LogicException;
use Lorisleiva\Actions\Concerns\AsFake;
use Lorisleiva\Actions\Concerns\AsObject;

/**
 * @method static BlogPublishingSurfaceData run(Site $site, ?Collection $languages = null, bool $createElements = true)
 */
class EnsureBlogPublishingSurfaceAction
{
    use AsFake;
    use AsObject;

    public function handle(Site $site, ?Collection $languages = null, bool $createElements = true): BlogPublishingSurfaceData
    {
        $blogCreator = resolve(BlogCreator::class);
        $languages ??= $site->getAllLanguages();

        if ($createElements) {
            $this->ensureSurfaceElements($blogCreator, $languages);
        }

        $blogPage = $blogCreator->createBlogPage(
            $site,
            type: $this->getPageType($blogCreator, BlogPageTypeEnum::Blog->value),
            layout: $blogCreator->createBlogPageLayout(),
            languages: $languages,
        );

        $archivesPage = $blogCreator->createArchivesPage(
            $blogPage,
            type: $this->getPageType($blogCreator, PageTypeEnum::System->value),
            layout: $blogCreator->createArchivesLayout(),
            languages: $languages,
        );

        $archivePage = $blogCreator->createArchivePage(
            $archivesPage,
            type: $this->getPageType($blogCreator, BlogPageTypeEnum::Archive->value),
            layout: $this->getResultsLayout(),
            languages: $languages,
        );

        $tagsPage = $blogCreator->createTagsPage(
            $site,
            $blogPage,
            languages: $languages,
            type: $this->getPageType($blogCreator, PageTypeEnum::System->value),
            layout: $blogCreator->createTagsLayout(),
        );

        $tagPage = $blogCreator->createTagPage(
            $site,
            $tagsPage,
            languages: $languages,
            type: $this->getPageType($blogCreator, BlogPageTypeEnum::Tag->value),
            layout: $this->getResultsLayout(),
        );

        $blogCreator->addPagesToNavigations(
            [NavigationHandle::Main->value, NavigationHandle::Footer->value],
            site: $site,
            pages: [$blogPage],
            languages: $languages,
        );

        return new BlogPublishingSurfaceData(
            blogPage: $blogPage,
            archivesPage: $archivesPage,
            archivePage: $archivePage,
            tagsPage: $tagsPage,
            tagPage: $tagPage,
        );
    }

    private function ensureSurfaceElements(BlogCreator $blogCreator, Collection $languages): void
    {
        $resultsElementType = resolve(LayoutTypeCreator::class)->resultsElementType();

        $blogCreator->createLatestArticlesElement($languages);
        $blogCreator->createArchivesElement($languages);
        $blogCreator->createTagsElement($languages);
        $blogCreator->relatedArticlesElement($resultsElementType, $languages);

        resolve(ElementCreator::class)->latestPagesElement($resultsElementType, $languages);
    }

    private function getPageType(BlogCreator $blogCreator, string $key): Blueprint
    {
        $type = Blueprint::query()->where('key', $key)->pageType()->first();

        if ($type instanceof Blueprint) {
            return $type;
        }

        $createdType = match ($key) {
            BlogPageTypeEnum::Archive->value => $blogCreator->createArchivePageType(),
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

    private function getResultsLayout(): Layout
    {
        return GetOrCreateResultsLayoutAction::run();
    }
}
