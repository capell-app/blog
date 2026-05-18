@php
    use Capell\Blog\Actions\GenerateArchiveUrl;
    use Capell\Blog\Data\ArchiveMonthData;
    use Capell\Blog\Enums\BlogTypeGroupEnum;
    use Capell\Blog\Support\Loader\BlogLoader;
    use Capell\Frontend\Facades\Frontend;
    use Illuminate\Contracts\Pagination\LengthAwarePaginator;
    use Illuminate\Support\Collection;

    $site = Frontend::site();
    $language = Frontend::language();
    $page = Frontend::page();
    $theme = Frontend::theme();
    $urlParams = Frontend::params();
@endphp

@props([
    'archiveDate' => $urlParams['archive_date'] ?? null,
    'container',
    'containerKey',
    'containerWidth' => null,
    'loop',
    'results',
    'showPageContent' => $elementData['meta']['show_page_content'] ?? false,
    'showPageTitle' => $elementData['meta']['show_page_title'] ?? false,
    'element',
])
@php
    $archivePage ??= BlogLoader::getArchivePage($site, $language);
    $archives ??= collect();

    if (! $archivePage) {
        $archives = collect();
    }

    if ($archivePage && $archives instanceof Collection && $archives->isEmpty()) {
        $archives = BlogLoader::getArchives(
            site: $site,
            language: $language,
            group: $element->meta['page_group'] ?? BlogTypeGroupEnum::Article->value,
            limit: $element->meta['limit'] ?? config('capell-frontend.pagination_limit', 12),
        );
    }

    if (! $archives instanceof Collection && ! $archives instanceof LengthAwarePaginator) {
        $archives = collect($archives)
            ->map(fn (ArchiveMonthData|array $archive): ArchiveMonthData => ArchiveMonthData::from($archive));
    }
@endphp

<x-capell-layout-builder::widget.wrapper
    class="capell-page-archives element element-{{ $element->key }}"
    :$container
    :$containerKey
    :$containerWidth
    :index="$loop->index"
    :widget="$element"
>
    @php
        $showTitle = $element->getMeta("container_options.{$containerKey}.hide_title") !== true
            && ($element->translation?->title || ($showPageTitle && $page->translation->title));
        $showContent = $element->getMeta("container_options.{$containerKey}.hide_content") !== true
            && ($element->translation?->content || ($showPageContent && $page->translation->content));
    @endphp

    @if ($showTitle || $showContent)
        <x-capell::content
            class="element-content mb-6"
            :compact="true"
            :content="$showContent ? ($element->translation->content ?: ($showPageContent ? $page->translation->content : null)) : null"
            :content-type="$element->type->content_structure"
            :divider="$element->getMeta('content_divider')"
            :text-align="$element->getMeta('align')"
            :title="$showTitle ? ($element->translation->title ?: ($showPageTitle ? $page->translation->title : null)) : null"
            :heading-style="$element->getMeta('heading_style')"
            :heading-tag="$showPageTitle ? 'h1' : null"
        />
    @endif

    @if ($archives?->isEmpty())
        <x-capell::no-results>
            {{ $element->translation->getMeta('no_results', __('capell-blog::messages.no_archives_found')) }}
        </x-capell::no-results>
    @else
        <ul
            class="element-archives-months @md:grid-cols-2 grid gap-x-6 divide-y divide-gray-100 dark:divide-gray-600"
        >
            @foreach ($archives as $archive)
                @php
                    $url = GenerateArchiveUrl::run($archivePage->pageUrl, $archive);
                    $active = $archiveDate && $archiveDate->month === $archive->month && $archiveDate->year === $archive->year;
                @endphp

                <x-capell::list.list-item
                    :$url
                    :count="$archive->total"
                    :active="$active"
                    size="sm"
                    class="element-archives-month px-2"
                >
                    {{ Carbon\Carbon::create()->day(1)->month($archive->month)->year($archive->year)->format('F Y') }}
                </x-capell::list.list-item>
            @endforeach
        </ul>
    @endif
</x-capell-layout-builder::widget.wrapper>
