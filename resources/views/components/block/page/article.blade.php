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
    $language = Frontend::language();
    $site = Frontend::site();
    $siteDomain = $site !== null && method_exists($site, 'relationLoaded') && $site->relationLoaded('siteDomain') ? $site->siteDomain : null;
    $author ??= $articleMetaData?->author;
    $pageTranslation = method_exists($page, 'relationLoaded') && $page->relationLoaded('translation')
        ? $page->getRelation('translation')
        : null;
    $pageType = method_exists($page, 'relationLoaded') && $page->relationLoaded('type')
        ? $page->getRelation('type')
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
    $blogUrl = $language !== null && method_exists($page, 'getParentUrl') ? $page->getParentUrl($language, true) : null;
    $homeUrl = $siteDomain?->url;
    $articleImage = method_exists($page, 'relationLoaded') && $page->relationLoaded('image')
        ? $page->getRelation('image')
        : null;
@endphp

<x-capell-foundation-theme::block.wrapper
    class="capell-page-article block block-{{ $block->key }}"
    :$container
    :$containerKey
    :$containerWidth
    :index="$loop->index"
    :widget="$block"
    container-class="capell-blog-article mx-auto flex max-w-5xl flex-col gap-12"
>
    <article class="grid gap-12">
        @if ($blogUrl || $homeUrl)
            <nav
                class="breadcrumbs text-sm text-slate-500"
                aria-label="{{ __('capell-frontend::generic.breadcrumbs') }}"
            >
                <ol class="flex flex-wrap items-center gap-x-2 gap-y-1">
                    @if ($homeUrl)
                        <li>
                            <a
                                href="{{ $homeUrl }}"
                                class="hover:text-primary focus:text-primary transition"
                                @wireNavigate
                            >
                                {{ __('capell-frontend::generic.home') }}
                            </a>
                        </li>
                    @endif

                    @if ($blogUrl)
                        <li aria-hidden="true" class="text-slate-300">/</li>
                        <li>
                            <a
                                href="{{ $blogUrl }}"
                                class="hover:text-primary focus:text-primary transition"
                                @wireNavigate
                            >
                                {{ __('capell-blog::generic.blog') }}
                            </a>
                        </li>
                    @endif

                    <li aria-hidden="true" class="text-slate-300">/</li>
                    <li aria-current="page" class="line-clamp-1 text-slate-600">
                        {{ $pageTranslation?->title }}
                    </li>
                </ol>
            </nav>
        @endif

        <header>
            <div class="max-w-4xl">
                <div
                    class="mb-5 flex flex-wrap items-center gap-x-4 gap-y-2 text-sm text-slate-500"
                >
                    <span
                        class="text-primary text-xs font-semibold tracking-[0.12em] uppercase"
                    >
                        {{ __('capell-blog::generic.article') }}
                    </span>

                    @if ($withDate && $publishedDate)
                        <x-capell-blog::page.published-date
                            class="whitespace-nowrap"
                            :date="$publishedDate"
                        />
                    @endif
                </div>

                <{{ $headingTag }}
                    class="max-w-4xl text-4xl leading-[1.05] font-semibold text-balance text-slate-950 md:text-6xl"
                >
                    {{ $pageTranslation?->title }}
                </{{ $headingTag }}>

                @if ($summary)
                    <p class="mt-6 max-w-3xl text-xl leading-9 text-slate-600">
                        {{ $summary }}
                    </p>
                @endif

                @if ($articleImage)
                    <figure
                        class="mt-10 overflow-hidden rounded-lg border border-slate-200 bg-slate-100 shadow-[0_24px_70px_rgb(15_23_42_/_0.12)]"
                    >
                        <img
                            src="{{ $articleImage->getUrl() }}"
                            alt="{{ $pageTranslation?->title }}"
                            class="aspect-[16/9] w-full object-cover"
                        />
                    </figure>
                @endif
            </div>
        </header>

        <div class="grid max-w-3xl">
            <x-capell::content
                class="capell-blog-article-content prose-headings:text-slate-950 prose-a:text-primary prose-p:leading-8 text-lg text-slate-700"
                :$containerKey
                :image="null"
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
                    'article-meta flex max-w-3xl flex-col gap-5 rounded-lg bg-slate-50/80 p-5 md:flex-row md:items-center md:justify-between dark:bg-slate-900/60',
                    'py-6' => $hasAuthorMeta,
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
                class="neighbor-links grid max-w-4xl gap-8 border-t border-slate-200 pt-8 md:grid-cols-2"
                aria-label="{{ __('capell-blog::generic.article_navigation') }}"
            >
                @if ($hasPreviousArticleLink)
                    <a
                        href="{{ $previousPageUrl }}"
                        title="{{ strip_tags((string) $previousPageTranslation?->title) }}"
                        class="group flex flex-col text-left"
                        @wireNavigate
                    >
                        <span
                            class="text-xs font-semibold tracking-[0.12em] text-slate-500 uppercase"
                        >
                            {{ __('capell-blog::generic.previous_article') }}
                        </span>
                        <span
                            class="group-hover:text-primary group-focus:text-primary mt-3 text-lg leading-snug font-semibold text-slate-950 transition"
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
                            'group flex flex-col text-left md:text-right',
                            'md:col-start-2' => ! $hasPreviousArticleLink,
                        ])
                        @wireNavigate
                    >
                        <span
                            class="text-xs font-semibold tracking-[0.12em] text-slate-500 uppercase"
                        >
                            {{ __('capell-blog::generic.next_article') }}
                        </span>
                        <span
                            class="group-hover:text-primary group-focus:text-primary mt-3 text-lg leading-snug font-semibold text-slate-950 transition"
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
