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
    'showPageContent' => $blockData['meta']['show_page_content'] ?? false,
    'showPageTitle' => $blockData['meta']['show_page_title'] ?? false,
    'block',
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
            group: $block->meta['page_group'] ?? BlogTypeGroupEnum::Article->value,
            limit: $block->meta['limit'] ?? config('capell-frontend.pagination_limit', 12),
        );
    }

    if (! $archives instanceof Collection && ! $archives instanceof LengthAwarePaginator) {
        $archives = collect($archives)
            ->map(fn (ArchiveMonthData|array $archive): ArchiveMonthData => ArchiveMonthData::from($archive));
    }
@endphp

<x-capell-foundation-theme::block.wrapper
    class="capell-page-archives block block-{{ $block->key }}"
    :$container
    :$containerKey
    :$containerWidth
    :index="$loop->index"
    :widget="$block"
>
    @php
        $showTitle = $block->getMeta("container_options.{$containerKey}.hide_title") !== true
            && ($block->translation?->title || ($showPageTitle && $page->translation->title));
        $showContent = $block->getMeta("container_options.{$containerKey}.hide_content") !== true
            && ($block->translation?->content || ($showPageContent && $page->translation->content));
    @endphp

    @if ($showTitle || $showContent)
        <x-capell::content
            class="block-content mb-6"
            :compact="true"
            :content="$showContent ? ($block->translation->content ?: ($showPageContent ? $page->translation->content : null)) : null"
            :content-type="$block->type->content_structure"
            :divider="$block->getMeta('content_divider')"
            :text-align="$block->getMeta('align')"
            :title="$showTitle ? ($block->translation->title ?: ($showPageTitle ? $page->translation->title : null)) : null"
            :heading-style="$block->getMeta('heading_style')"
            :heading-tag="$showPageTitle ? 'h1' : null"
        />
    @endif

    @if ($archives?->isEmpty())
        <x-capell::no-results>
            {{ $block->translation->getMeta('no_results', __('capell-blog::messages.no_archives_found')) }}
        </x-capell::no-results>
    @else
        <ul
            class="block-archives-months @md:grid-cols-2 grid gap-x-6 divide-y divide-gray-100 dark:divide-gray-600"
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
                    class="block-archives-month px-2"
                >
                    {{ Carbon\Carbon::create()->day(1)->month($archive->month)->year($archive->year)->format('F Y') }}
                </x-capell::list.list-item>
            @endforeach
        </ul>
    @endif
</x-capell-foundation-theme::block.wrapper>
