@php
    use Capell\Core\Actions\ResolveRenderableComponentAction;
    use Capell\Core\Enums\AssetComponentEnum;
    use Capell\Core\Enums\RenderableTypeEnum;
    use Capell\Frontend\Enums\RenderHookLocation;
    use Capell\Frontend\Facades\Frontend;
    use Capell\Frontend\Support\Render\RenderHookRegistry;
    use Capell\Frontend\Support\View\PublicModelMeta;
    use Illuminate\Contracts\Pagination\LengthAwarePaginator;

    $language = Frontend::language();
    $componentItem = ResolveRenderableComponentAction::run(RenderableTypeEnum::Asset, $componentItem ?? AssetComponentEnum::Card->value);
    $latestArticles ??= collect();
    $sidebarTags ??= collect();
    $currentPageIsEmpty = ! $results || $results->isEmpty();
    $isPaginator = $results instanceof LengthAwarePaginator;
    $currentPage = $isPaginator ? $results->currentPage() : 1;
    $perPage = $isPaginator ? $results->perPage() : ($results ? $results->count() : 0);
    $total = $isPaginator ? $results->total() : ($results ? $results->count() : 0);
    $from = $total > 0 ? (($currentPage - 1) * $perPage) + 1 : 0;
    $to = $total > 0 ? (($currentPage - 1) * $perPage) + count($results->items()) : 0;
    $lastPage = $isPaginator ? $results->lastPage() : 1;
    $range = $total > 0 ? $from . '-' . $to : '0';
    $summary = __('capell-frontend::messages.pagination_info', [
        'from' => $from,
        'to' => $to,
        'total' => $total,
    ]);
@endphp

<div
    class="capell-page-results-slot capell-blog-results results @container mx-auto w-full max-w-[1200px] px-[6%] pb-12 xl:px-0"
    data-blog-results
>
    <style>
        .capell-blog-results-region {
            transition:
                min-height 260ms ease,
                opacity 220ms ease,
                transform 220ms ease;
        }

        .capell-blog-results-layout {
            display: grid;
            gap: 2rem;
        }

        @media (min-width: 1024px) {
            .capell-blog-results-layout {
                align-items: start;
                grid-template-columns: minmax(0, 1fr) 20rem;
            }
        }

        .capell-blog-results-region.is-loading {
            opacity: 0.58;
            transform: translateY(0.25rem);
        }

        .capell-blog-results-region.is-entering {
            animation: capell-blog-results-enter 280ms ease both;
        }

        @keyframes capell-blog-results-enter {
            from {
                opacity: 0.62;
                transform: translateY(0.5rem);
            }

            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        @media (prefers-reduced-motion: reduce) {
            .capell-blog-results-region,
            .capell-blog-results-region.is-entering {
                animation: none;
                transition: none;
            }
        }
    </style>

    <script>
        ;(() => {
            const storageKey = 'capell-blog-results-height'

            const resultsTop = () =>
                document.querySelector('#capell-blog-results-top')
            const resultsRegion = () =>
                document.querySelector('[data-blog-results-region]')

            const prepareTransition = () => {
                const region = resultsRegion()

                if (!region) {
                    return
                }

                sessionStorage.setItem(storageKey, String(region.offsetHeight))
                region.style.minHeight = `${region.offsetHeight}px`
                region.classList.add('is-loading')
                resultsTop()?.scrollIntoView({
                    behavior: 'smooth',
                    block: 'start',
                })
            }

            const animateNewRegion = () => {
                const region = resultsRegion()
                const storedHeight = Number(
                    sessionStorage.getItem(storageKey) || 0,
                )

                if (!region || storedHeight <= 0) {
                    return
                }

                sessionStorage.removeItem(storageKey)
                region.style.minHeight = `${storedHeight}px`
                region.classList.add('is-entering')

                window.setTimeout(() => {
                    region.style.minHeight = ''
                    region.classList.remove('is-loading', 'is-entering')
                }, 320)
            }

            document.addEventListener(
                'click',
                (event) => {
                    const link =
                        event.target instanceof Element
                            ? event.target.closest(
                                  '[data-blog-results] .pagination-links__link',
                              )
                            : null

                    if (link) {
                        prepareTransition()
                    }
                },
                true,
            )

            document.addEventListener('livewire:navigated', animateNewRegion)
            window.addEventListener('pageshow', animateNewRegion)
        })()
    </script>

    <div id="capell-blog-results-top" class="scroll-mt-24"></div>

    <section
        class="mb-8 grid gap-5 rounded-lg border border-slate-200 bg-white p-5 shadow-sm md:p-8 lg:grid-cols-[minmax(0,1fr)_minmax(18rem,0.45fr)] lg:items-end"
        aria-labelledby="capell-blog-results-heading"
    >
        <div class="grid gap-4">
            <p
                class="text-xs font-extrabold tracking-[0.08em] text-[#0f766e] uppercase"
            >
                {{ __('capell-blog::generic.article_archives') }}
            </p>

            <h2
                id="capell-blog-results-heading"
                class="max-w-3xl font-[Manrope] text-3xl leading-[1.06] font-extrabold tracking-normal text-balance text-slate-950 md:text-5xl"
            >
                {{ $total === 0 ? __('capell-blog::messages.no_articles_found') : $summary }}
            </h2>

            <p
                class="max-w-3xl text-base leading-8 text-pretty text-slate-600 md:text-lg"
            >
                {{ __('capell-blog::messages.blog_listing_intro') }}
            </p>
        </div>

        <dl
            class="grid gap-3 rounded-lg border border-slate-200 bg-slate-50 p-4 text-sm font-bold text-slate-700 sm:grid-cols-3 lg:grid-cols-1"
            aria-label="{{ $summary }}"
        >
            <div>
                <dt
                    class="text-xs font-extrabold tracking-[0.08em] text-[#0f766e] uppercase"
                >
                    {{ __('capell-blog::messages.showing_articles') }}
                </dt>
                <dd class="mt-1 text-2xl font-extrabold text-slate-950">
                    {{ $range }}
                </dd>
            </div>
            <div>
                <dt
                    class="text-xs font-extrabold tracking-[0.08em] text-[#0f766e] uppercase"
                >
                    {{ __('capell-blog::messages.total_articles') }}
                </dt>
                <dd class="mt-1 text-2xl font-extrabold text-slate-950">
                    {{ $total }}
                </dd>
            </div>
            <div>
                <dt
                    class="text-xs font-extrabold tracking-[0.08em] text-[#0f766e] uppercase"
                >
                    {{ __('capell-blog::messages.article_page') }}
                </dt>
                <dd class="mt-1 text-2xl font-extrabold text-slate-950">
                    {{ $currentPage }} / {{ max(1, $lastPage) }}
                </dd>
            </div>
        </dl>
    </section>

    <p class="sr-only" role="status" aria-live="polite">
        {{ $total }} {{ __('capell-frontend::messages.results_found') }}
    </p>

    <div
        class="capell-blog-results-region capell-blog-results-layout"
        data-blog-results-region
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
                    class="grid w-full max-w-full min-w-0 gap-5 overflow-hidden"
                    role="list"
                >
                    @foreach ($results as $item)
                        @php
                            $author = method_exists($item, 'relationLoaded') && $item->relationLoaded('creator') ? $item->creator : null;
                            $image = method_exists($item, 'relationLoaded') && $item->relationLoaded('image') ? $item->image : null;
                            $pageUrl = method_exists($item, 'relationLoaded') && $item->relationLoaded('pageUrl') ? $item->pageUrl : null;
                            $translation = method_exists($item, 'relationLoaded') && $item->relationLoaded('translation') ? $item->translation : null;
                            $squareImage = (bool) PublicModelMeta::get($item, 'square_image', false);
                            $publishDatePosition = PublicModelMeta::get($translation, 'publish_date_position', 'top');
                        @endphp

                        {!! app(RenderHookRegistry::class)->renderAll(RenderHookLocation::BeforeResult, $item) !!}
                        <x-dynamic-component
                            :component="$componentItem"
                            :$loop
                            :asset="$item"
                            :author="$author"
                            :image="$image"
                            :link-text="__('capell-blog::generic.read_article')"
                            :publish-date="$item->getPublishDate()"
                            :summary="$translation?->summary"
                            :title="$translation?->title"
                            :url="$pageUrl?->full_url"
                            :square-image="$squareImage"
                            :with-summary="true"
                            :with-author="true"
                            :publish-date-position="$publishDatePosition"
                            role="listitem"
                        />
                        {!! app(RenderHookRegistry::class)->renderAll(RenderHookLocation::AfterResult, $item) !!}
                    @endforeach
                </div>
            @endif
        </div>

        <aside class="grid gap-5 lg:sticky lg:top-24">
            <section
                class="rounded-lg border border-slate-200 bg-white p-5 shadow-sm"
            >
                <h2
                    class="text-lg leading-tight font-extrabold tracking-normal text-slate-950"
                >
                    {{ __('capell-blog::generic.tags') }}
                </h2>

                @if ($sidebarTags->isNotEmpty())
                    <div class="mt-4 flex flex-wrap gap-2">
                        @foreach ($sidebarTags as $tag)
                            @php
                                $tagName = $tag->translate('name', $language->code);
                                $tagUrl = $tagPage ? $tag->getUrl($tagPage, $language) : null;
                            @endphp

                            @if ($tagUrl)
                                <a
                                    class="rounded bg-slate-100 px-3 py-2 text-sm font-bold text-slate-700 no-underline transition hover:bg-blue-50 hover:text-blue-700 focus:bg-blue-50 focus:text-blue-700"
                                    href="{{ $tagUrl }}"
                                    @wireNavigate
                                >
                                    {{ $tagName }}
                                </a>
                            @else
                                <span
                                    class="rounded bg-slate-100 px-3 py-2 text-sm font-bold text-slate-700"
                                >
                                    {{ $tagName }}
                                </span>
                            @endif
                        @endforeach
                    </div>
                @else
                    <p class="mt-3 text-sm leading-6 text-slate-500">
                        {{ __('capell-blog::messages.no_tags_found') }}
                    </p>
                @endif
            </section>

            <section
                class="rounded-lg border border-slate-200 bg-white p-5 shadow-sm"
            >
                <h2
                    class="text-lg leading-tight font-extrabold tracking-normal text-slate-950"
                >
                    {{ __('capell-blog::generic.latest_articles') }}
                </h2>

                @if ($latestArticles->isNotEmpty())
                    <div class="mt-4 grid gap-4">
                        @foreach ($latestArticles as $article)
                            @php
                                $articleUrl = method_exists($article, 'relationLoaded') && $article->relationLoaded('pageUrl') ? $article->pageUrl : null;
                                $articleTranslation = method_exists($article, 'relationLoaded') && $article->relationLoaded('translation') ? $article->translation : null;
                            @endphp

                            <a
                                class="block border-t border-slate-200 pt-4 text-slate-950 no-underline transition first:border-t-0 first:pt-0 hover:text-blue-700 focus:text-blue-700"
                                href="{{ $articleUrl?->full_url }}"
                                @wireNavigate
                            >
                                @if ($article->getPublishDate())
                                    <time
                                        class="text-xs font-bold tracking-[0.08em] text-slate-500 uppercase"
                                        datetime="{{ $article->getPublishDate()?->toW3cString() }}"
                                    >
                                        {{ $article->getPublishDate()?->format(config('capell-frontend.date_format')) }}
                                    </time>
                                @endif

                                <strong
                                    class="mt-1 block text-base leading-snug font-extrabold"
                                >
                                    {{ $articleTranslation?->title }}
                                </strong>
                            </a>
                        @endforeach
                    </div>
                @else
                    <p class="mt-3 text-sm leading-6 text-slate-500">
                        {{ __('capell-blog::messages.no_articles_found') }}
                    </p>
                @endif
            </section>
        </aside>
    </div>

    @if ($isPaginator)
        <x-capell::pagination
            :$results
            :wire-links="false"
            scroll-to-element="#capell-blog-results-top"
            class="my-6 mt-10 flex flex-col items-center justify-center gap-5 rounded-lg border border-slate-200 bg-white p-4 shadow-sm lg:mt-14 dark:text-white"
        />
    @endif
</div>
