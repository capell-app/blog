@props([
    'container',
    'containerKey',
    'containerWidth' => null,
    'loop',
    'results',
    'widget',
])

<x-capell-theme-foundation::widget.wrapper
    class="capell-page-archives widget widget-{{ $widget->key }}"
    :$container
    :$containerKey
    :$containerWidth
    :index="$loop->index"
    :widget="$widget"
>
    @if ($contentData->show)
        <x-capell::content
            class="widget-content mb-6"
            :compact="true"
            :content="$contentData->content"
            :content-type="$contentData->contentType"
            :divider="$contentData->divider"
            :text-align="$contentData->textAlign"
            :title="$contentData->title"
            :heading-style="$contentData->headingStyle"
            :heading-tag="$contentData->headingTag"
        />
    @endif

    @if ($archiveLinks === [])
        <x-capell::no-results>
            {{ $noResultsText }}
        </x-capell::no-results>
    @else
        <ul
            class="widget-archives-months @md:grid-cols-2 grid gap-x-6 divide-y divide-gray-100 dark:divide-gray-600"
        >
            @foreach ($archiveLinks as $archiveLink)
                <x-capell::list.list-item
                    :url="$archiveLink->url"
                    :count="$archiveLink->count"
                    :active="$archiveLink->active"
                    size="sm"
                    class="widget-archives-month px-2"
                >
                    {{ $archiveLink->label }}
                </x-capell::list.list-item>
            @endforeach
        </ul>
    @endif
</x-capell-theme-foundation::widget.wrapper>
