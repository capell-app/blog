@php
    use Capell\Core\Actions\ResolveRenderableComponentAction;
    use Capell\Core\Enums\AssetComponentEnum;
    use Capell\Core\Enums\RenderableTypeEnum;
    use Capell\Frontend\Enums\RenderHookLocation;
    use Capell\Frontend\Facades\Frontend;
    use Capell\Frontend\Support\Render\RenderHookRegistry;
    use Illuminate\Contracts\Pagination\LengthAwarePaginator;

    $componentItem = ResolveRenderableComponentAction::run(RenderableTypeEnum::Asset, $componentItem ?? AssetComponentEnum::Card->value);
    $currentPageIsEmpty = ! $results || $results->isEmpty();
    $isPaginator = $results instanceof LengthAwarePaginator;
    $total = $isPaginator ? $results->total() : ($results ? $results->count() : 0);
    $columns ??= 1;
    $withImage ??= false;
    $withPaginationSummary ??= true;
    $resultItems ??= [];
@endphp

<div
    class="capell-page-results-slot capell-blog-results results @container mx-auto w-full max-w-[1200px] px-[6%] pb-12 xl:px-0"
    data-blog-results
>
    <div
        id="capell-blog-results-top"
        class="scroll-mt-24"
    ></div>

    <p
        class="sr-only"
        role="status"
        aria-live="polite"
    >
        {{ $total }} {{ __('capell-frontend::messages.results_found') }}
    </p>

    <div
        class="capell-blog-results-region"
        data-blog-results-region
        wire:loading.class="opacity-55"
        wire:target="gotoPage,nextPage,previousPage"
    >
        <div class="min-w-0">
            @if ($currentPageIsEmpty)
                <div
                    class="rounded-lg border border-slate-200 bg-white p-5 shadow-sm md:p-8"
                >
                    <x-capell::no-results>
                        {!! $noResultsText !!}
                    </x-capell::no-results>
                </div>
            @else
                <div
                    @class([
                        'grid w-full max-w-full min-w-0 gap-5 overflow-hidden transition-opacity duration-150',
                        '@3xl:grid-cols-2' => $columns >= 2,
                        '@7xl:grid-cols-3' => $columns >= 3,
                    ])
                    role="list"
                >
                    @foreach ($results as $item)
                        @php
                            $resultItem = $resultItems[$loop->index] ?? null;
                        @endphp

                        {!! app(RenderHookRegistry::class)->renderAll(RenderHookLocation::BeforeResult, $item) !!}
                        <x-dynamic-component
                            :component="$componentItem"
                            :$loop
                            :asset="$item"
                            :author="$resultItem?->author"
                            :image="$withImage ? $resultItem?->image : null"
                            :link-text="__('capell-blog::generic.read_article')"
                            :publish-date="$item->getPublishDate()"
                            :summary="$resultItem?->translation?->summary"
                            :title="$resultItem?->translation?->title"
                            :url="$resultItem?->url"
                            :square-image="$resultItem?->squareImage ?? false"
                            :with-summary="true"
                            :with-author="true"
                            :publish-date-position="$resultItem?->publishDatePosition ?? 'top'"
                            class="capell-blog-article-card"
                            role="listitem"
                        />
                        {!! app(RenderHookRegistry::class)->renderAll(RenderHookLocation::AfterResult, $item) !!}
                    @endforeach
                </div>
            @endif
        </div>
    </div>

    @if ($isPaginator)
        <x-capell::pagination
            :$results
            :wire-links="true"
            :with-summary="$withPaginationSummary"
            scroll-to-element="#capell-blog-results-top"
            class="my-6 mt-10 flex flex-col items-center justify-center gap-5 rounded-lg border border-slate-200 bg-white/95 p-4 shadow-sm ring-1 ring-slate-950/5 transition lg:mt-14 dark:border-slate-700 dark:bg-slate-900 dark:text-white"
        />
    @endif
</div>
