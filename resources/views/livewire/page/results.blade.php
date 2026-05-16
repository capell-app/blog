@php
    use Capell\Core\Enums\AssetComponentEnum;
    use Capell\Frontend\Facades\Frontend;
    use Capell\Frontend\Support\View\DeferredHtmlable;

    $page = Frontend::page();

    $component = $page['meta']['component'] ?? AssetComponentEnum::Page->value;
    $componentItem = $page['meta']['component_item'] ?? AssetComponentEnum::Card->value;
    $pageTranslation = $page?->relationLoaded('translation') ? $page->translation : null;
    $noResultsText = $pageTranslation?->meta['no_results'] ?? null;
    $results = $this->results;

    $pageSlot = new DeferredHtmlable(
        fn (): string => view(
            'capell-blog::livewire.page.results-slot',
            [
                'results' => $results,
                'component' => $component,
                'componentItem' => $componentItem,
                'noResultsText' => $noResultsText,
            ],
        )->render(),
    );
@endphp

<div class="capell-blog-page">
    <x-capell::layout
        class="layout-results capell-blog-results-shell"
        main-class="capell-blog-results-main"
        main-container-class="capell-blog-results-container"
        :page-slot="$pageSlot"
    >
        {{ $pageSlot }}
    </x-capell::layout>
</div>
