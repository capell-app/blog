@php
    use Capell\Frontend\Support\View\DeferredHtmlable;

    $results = $this->results;
    $component = $blogResultsViewData->component;
    $componentItem = $blogResultsViewData->componentItem;
    $noResultsText = $blogResultsViewData->noResultsText;
    $columns = $blogResultsViewData->columns;
    $withImage = $blogResultsViewData->withImage;
    $withPaginationSummary = $blogResultsViewData->withPaginationSummary;
    $resultItems = $blogResultsViewData->resultItems;

    $pageSlot = new DeferredHtmlable(
        fn (): string => view(
            'capell-blog::livewire.page.results-slot',
            [
                'results' => $results,
                'component' => $component,
                'componentItem' => $componentItem,
                'latestArticles' => $latestArticles ?? null,
                'noResultsText' => $noResultsText,
                'columns' => $columns,
                'withImage' => $withImage,
                'withPaginationSummary' => $withPaginationSummary,
                'resultItems' => $resultItems,
                'sidebarTags' => $sidebarTags ?? null,
                'tagPage' => $tagPage ?? null,
            ],
        )->render(),
    );
@endphp

<div class="capell-page-results capell-blog-page">
    <x-capell::layout
        class="layout-results capell-blog-results-shell"
        main-class="capell-blog-results-main"
        main-container-class="capell-blog-results-container"
        :page-slot="$pageSlot"
    >
        {{ $pageSlot }}
    </x-capell::layout>
</div>
