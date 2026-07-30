<?php

declare(strict_types=1);

namespace Capell\Blog\Actions;

use Capell\Blog\Enums\BlogTypeGroupEnum;
use Capell\Blog\Models\Article;
use Capell\Blog\Support\Loader\TagLoader;
use Capell\Core\Enums\PageOrderEnum;
use Capell\Core\Models\Language;
use Capell\Core\Models\Site;
use Capell\Frontend\Contracts\FrontendContextReader;
use Capell\Frontend\Data\PageListingRequestData;
use Capell\Frontend\Support\Loader\PageLoader;
use Lorisleiva\Actions\Concerns\AsFake;
use Lorisleiva\Actions\Concerns\AsObject;

final class PrepareBlogFooterRenderDataAction
{
    use AsFake;
    use AsObject;

    public function handle(FrontendContextReader $context, Site $site, Language $language): void
    {
        if ($context->getFrontendData('blog.sidebar_tags') !== null) {
            return;
        }

        $context->setFrontendData('blog.sidebar_tags', TagLoader::getTags($site, $language, limit: 5, hasArticles: true));
        $context->setFrontendData('blog.tag_page', TagLoader::getTagResultsPage($site, $language));
        $context->setFrontendData('blog.latest_articles', PageLoader::list(new PageListingRequestData(
            language: $language,
            site: $site,
            limit: 4,
            ordering: PageOrderEnum::Latest,
            pageGroup: BlogTypeGroupEnum::Article,
            withImage: true,
            morphModel: Article::class,
        )));
    }
}
