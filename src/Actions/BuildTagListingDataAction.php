<?php

declare(strict_types=1);

namespace Capell\Blog\Actions;

use Capell\Blog\Data\TagListingData;
use Capell\Blog\Support\Loader\TagLoader;
use Capell\Core\Models\Language;
use Capell\Core\Models\Site;
use Lorisleiva\Actions\Concerns\AsObject;

final class BuildTagListingDataAction
{
    use AsObject;

    public function handle(
        Site $site,
        Language $language,
        ?int $limit = null,
        ?int $paginationPage = null,
        bool $withPagination = false,
        string $paginationKey = 'tags',
    ): TagListingData {
        $limit ??= $withPagination
            ? config('capell-frontend.pagination_limit', 10)
            : config('capell-blog.tag_listing_limit', 50);

        return new TagListingData(
            tags: TagLoader::getTags(
                site: $site,
                language: $language,
                limit: $limit,
                hasArticles: true,
                paginationPage: $paginationPage,
                withPagination: $withPagination,
                paginationKey: $paginationKey,
            ),
            tagPage: TagLoader::getTagResultsPage($site, $language),
        );
    }
}
