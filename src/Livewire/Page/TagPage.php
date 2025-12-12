<?php

declare(strict_types=1);

namespace Capell\Blog\Livewire\Page;

use Capell\Blog\Enums\ModelEnum;
use Capell\Blog\Models\Tag;
use Capell\Core\Facades\CapellCore;
use Capell\Frontend\Facades\Frontend;
use Capell\Frontend\Livewire\Page\AbstractPage;
use Capell\Frontend\Services\Loader\PageLoader;
use Capell\Frontend\Services\State\FrontendState;
use Illuminate\Contracts\Database\Eloquent\Builder as BuilderContract;
use Illuminate\Database\Eloquent\Builder;
use Override;

class TagPage extends AbstractPage
{
    public ?string $tagSlug = null;

    protected static string $defaultView = 'capell::livewire.page.results';

    protected Tag $tag;

    protected ?string $tagName = null;

    #[Override]
    protected function getViewData(): array
    {
        return [
            'tag_slug' => $this->tagSlug,
            'tag_name' => $this->tagName,
        ];
    }

    protected function loadPage(): void
    {
        $params = Frontend::params();
        $this->tagSlug = is_array($params) ? ($params['slug'] ?? null) : null;

        if (in_array($this->tagSlug, ['', '0', null], true)) {
            abort(404);

            return;
        }

        $language = Frontend::language();
        $page = Frontend::page();
        $site = Frontend::site();

        /** @var class-string<Tag> $model */
        $model = CapellCore::getModel(ModelEnum::Tag);

        $tag = $model::query()->where('type', 'page')
            ->where('slug->' . $language->code, $this->tagSlug)
            ->first();

        if (! $tag) {
            abort(404);

            return;
        }

        $this->tag = $tag;

        $this->tagName = $this->tag->getTranslation('name', $language->code);

        $paginationKey = config('capell-admin.page_query', 'pageQuery');

        $this->results = PageLoader::getPages(
            language: $language,
            site: $site,
            limit: $this->widget->meta['limit'] ?? config('capell-frontend.pagination_limit', 12),
            paginationPage: $this->getPage($paginationKey),
            withChildrenCount: $page->type->meta['with_children_count'] ?? true,
            withImage: $page->type->meta['with_image'] ?? true,
            withPagination: $page->type->meta['pagination'] ?? true,
            withParent: $page->type->meta['with_parent'] ?? true,
            withDate: $page->type->meta['with_date'] ?? true,
            paginationKey: $paginationKey,
            cacheKeyPrepend: 'tagged-' . $this->tag->id,
            modifyQuery: fn (Builder $query) => $query->whereHas(
                'tags',
                fn (BuilderContract $query) => $query->where('taggable_type', 'page')
                    ->where('taggables.tag_id', $this->tag->id),
            ),
        );

        $this->params = $this->getViewData();

        resolve(FrontendState::class)->withParams($this->params);
    }
}
