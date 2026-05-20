@php
    use Capell\Frontend\Enums\RenderHookLocation;
    use Capell\Frontend\Facades\Frontend;
    use Capell\Frontend\Support\Render\RenderHookRegistry;

    $page = Frontend::page();
    $theme = Frontend::theme();
@endphp

@props([
    'container',
    'containerKey',
    'containerWidth' => null,
    'loop',
    'block',
    'headingSize' => $block->getMeta('heading_size', 'h1'),
    'withAuthor' => (bool) $block->getMeta('with_author'),
    'withDate' => (bool) $block->getMeta('with_date'),
    'withNextPrev' => (bool) $block->getMeta('with_next_prev'),
])
@php
    $nextPage ??= null;
    $previousPage ??= null;
    $articleMetaData ??= null;
    $author ??= $articleMetaData?->author;
    $pageTranslation = method_exists($page, 'relationLoaded') && $page->relationLoaded('translation')
        ? $page->getRelation('translation')
        : null;
    $pageType = method_exists($page, 'relationLoaded') && $page->relationLoaded('type')
        ? $page->getRelation('type')
        : null;
    $pageImage = method_exists($page, 'relationLoaded') && $page->relationLoaded('image')
        ? $page->getRelation('image')
        : null;
    $secondaryContainers = $theme?->secondary_containers ?? ['sidebar'];
    $publishedDate = $page->visible_from ?: $page->created_at;
    $summary = $pageTranslation?->summary ?: null;
    $articleMeta = app(RenderHookRegistry::class)->renderAll(
        RenderHookLocation::ArticleMeta,
        [
            'withAuthor' => $withAuthor,
            'author' => $author,
            'articleMetaData' => $articleMetaData,
        ],
    );

    $hasDefaultArticleMeta = $articleMetaData?->shouldRender() ?? false;
    $headingTag = in_array($headingSize, ['h1', 'h2', 'h3', 'h4', 'h5', 'h6'], true) ? $headingSize : 'h1';
    $previousPageUrlModel = $previousPage !== null && method_exists($previousPage, 'relationLoaded') && $previousPage->relationLoaded('pageUrl')
        ? $previousPage->getRelation('pageUrl')
        : null;
    $nextPageUrlModel = $nextPage !== null && method_exists($nextPage, 'relationLoaded') && $nextPage->relationLoaded('pageUrl')
        ? $nextPage->getRelation('pageUrl')
        : null;
    $previousPageUrl = is_string($previousPageUrlModel?->url ?? null) && $previousPageUrlModel->url !== ''
        ? $previousPageUrlModel->full_url
        : null;
    $nextPageUrl = is_string($nextPageUrlModel?->url ?? null) && $nextPageUrlModel->url !== ''
        ? $nextPageUrlModel->full_url
        : null;
    $previousPageTranslation = $previousPage !== null && method_exists($previousPage, 'relationLoaded') && $previousPage->relationLoaded('translation')
        ? $previousPage->getRelation('translation')
        : null;
    $nextPageTranslation = $nextPage !== null && method_exists($nextPage, 'relationLoaded') && $nextPage->relationLoaded('translation')
        ? $nextPage->getRelation('translation')
        : null;
    $hasPreviousArticleLink = $previousPage && $previousPageUrl && $previousPageTranslation;
    $hasNextArticleLink = $nextPage && $nextPageUrl && $nextPageTranslation;
    $hasAuthorMeta = $withAuthor && $articleMetaData?->author;
    $hasTagMeta = $articleMetaData?->tags->isNotEmpty() ?? false;
@endphp

<x-capell-foundation-theme::block.wrapper
    class="capell-page-article block block-{{ $block->key }}"
    :$container
    :$containerKey
    :$containerWidth
    :index="$loop->index"
    :widget="$block"
    container-class="capell-blog-article flex flex-col gap-10"
>
    <article class="grid gap-10">
        <header class="border-b border-slate-200 pb-8">
            <div class="max-w-4xl">
                <div class="mb-5 flex flex-wrap items-center gap-x-4 gap-y-2">
                    <span
                        class="text-primary text-xs font-semibold uppercase tracking-[0.12em]"
                    >
                        {{ __('capell-blog::generic.article') }}
                    </span>

                    @if ($withDate && $publishedDate)
                        <x-capell-blog::page.published-date
                            class="whitespace-nowrap text-sm text-slate-500"
                            :date="$publishedDate"
                        />
                    @endif
                </div>

                <{{ $headingTag }}
                    class="max-w-3xl text-balance text-4xl font-semibold leading-tight text-slate-950 md:text-5xl"
                >
                    {{ $pageTranslation?->title }}
                </{{ $headingTag }}>

                @if ($summary)
                    <p class="mt-5 max-w-3xl text-lg leading-8 text-slate-600">
                        {{ $summary }}
                    </p>
                @endif
            </div>
        </header>

        <div class="grid">
            <x-capell::content
                class="capell-blog-article-content prose-headings:text-slate-950 prose-a:text-primary prose-p:leading-8 max-w-3xl text-slate-700"
                :$containerKey
                :image="$pageImage"
                :heading-size="$headingSize"
                :content="$pageTranslation?->content"
                :content-type="$pageType?->content_structure"
                :muted="in_array($containerKey, $secondaryContainers)"
                :text-align="$block->getMeta('align')"
                :title="null"
                :image-title="$pageTranslation?->title"
                :heading-style="$block->getMeta('heading_style')"
                width="content"
            />
        </div>

        @if ($articleMeta !== '')
            {!! $articleMeta !!}
        @elseif ($hasDefaultArticleMeta)
            <div
                @class([
                    'article-meta flex flex-col gap-5 md:flex-row md:items-center md:justify-between',
                    'border-y border-slate-200 py-6' => $hasAuthorMeta,
                    'pt-2' => ! $hasAuthorMeta && $hasTagMeta,
                ])
            >
                @if ($hasAuthorMeta)
                    <x-capell-blog::page.author
                        class="min-w-0"
                        :author="$articleMetaData->author"
                    />
                @endif

                @if ($hasTagMeta)
                    <div
                        class="article-tags flex flex-col gap-x-10 gap-y-4 md:items-end"
                    >
                        <x-capell-blog::page.tags
                            :tagPage="$articleMetaData->tagPage"
                            :tags="$articleMetaData->tags"
                            with_tag_icon="true"
                        />
                    </div>
                @endif
            </div>
        @endif

        @if ($withNextPrev && ($hasPreviousArticleLink || $hasNextArticleLink))
            <nav
                class="neighbor-links grid gap-4 border-t border-slate-200 pt-8 md:grid-cols-2"
                aria-label="{{ __('capell-blog::generic.article_navigation') }}"
            >
                @if ($hasPreviousArticleLink)
                    <a
                        href="{{ $previousPageUrl }}"
                        title="{{ strip_tags((string) $previousPageTranslation?->title) }}"
                        class="hover:border-primary/40 focus:border-primary/40 group flex min-h-36 flex-col justify-between rounded-lg border border-slate-200 bg-white p-5 text-left transition"
                        @wireNavigate
                    >
                        <span
                            class="text-xs font-semibold uppercase tracking-[0.12em] text-slate-500"
                        >
                            {{ __('capell-blog::generic.previous_article') }}
                        </span>
                        <span
                            class="group-hover:text-primary group-focus:text-primary mt-4 text-lg font-semibold leading-snug text-slate-950"
                        >
                            {{ strip_tags((string) $previousPageTranslation?->label) }}
                        </span>
                        @if ($previousPageTranslation?->summary)
                            <span
                                class="mt-2 line-clamp-2 text-sm leading-6 text-slate-500"
                            >
                                {{ strip_tags((string) $previousPageTranslation->summary) }}
                            </span>
                        @endif
                    </a>
                @endif

                @if ($hasNextArticleLink)
                    <a
                        href="{{ $nextPageUrl }}"
                        title="{{ strip_tags((string) $nextPageTranslation?->title) }}"
                        @class([
                            'hover:border-primary/40 focus:border-primary/40 group flex min-h-36 flex-col justify-between rounded-lg border border-slate-200 bg-white p-5 text-left transition md:text-right',
                            'md:col-start-2' => ! $hasPreviousArticleLink,
                        ])
                        @wireNavigate
                    >
                        <span
                            class="text-xs font-semibold uppercase tracking-[0.12em] text-slate-500"
                        >
                            {{ __('capell-blog::generic.next_article') }}
                        </span>
                        <span
                            class="group-hover:text-primary group-focus:text-primary mt-4 text-lg font-semibold leading-snug text-slate-950"
                        >
                            {{ strip_tags((string) $nextPageTranslation?->label) }}
                        </span>
                        @if ($nextPageTranslation?->summary)
                            <span
                                class="mt-2 line-clamp-2 text-sm leading-6 text-slate-500"
                            >
                                {{ strip_tags((string) $nextPageTranslation->summary) }}
                            </span>
                        @endif
                    </a>
                @endif
            </nav>
        @endif
    </article>
</x-capell-foundation-theme::block.wrapper>
