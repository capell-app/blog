@props([
    'container',
    'containerKey',
    'containerWidth' => null,
    'loop',
    'widget',
])

<x-capell-theme-foundation::widget.wrapper
    class="capell-tag-tags widget widget-{{ $widget->key }} widget-tags"
    :$container
    :$containerKey
    :$containerWidth
    :$containerWidth
    :index="$loop->index"
    :widget="$widget"
>
    @if ($contentData->show)
        <x-capell::content
            class="mt-10 mb-6"
            :compact="true"
            :content="$contentData->content"
            :content-type="$contentData->contentType"
            :divider="$contentData->divider"
            :muted="$contentData->muted"
            :text-align="$contentData->textAlign"
            :title="$contentData->title"
            :heading-style="$contentData->headingStyle"
            :heading-tag="$contentData->headingTag"
        />
    @endif

    @if ($tagLinks === [])
        <x-capell::no-results> {{ $noResultsText }} </x-capell::no-results>
    @else
        <ul class="flex flex-wrap gap-2">
            @foreach ($tagLinks as $tagLink)
                <li>
                    <x-capell-blog::tag
                        :url="$tagLink->url"
                        :$withDarkMode
                    >
                        {{ $tagLink->name }}
                        @if ($tagLink->count !== null)
                            <x-slot:count>
                                ({{ $tagLink->count }})
                            </x-slot:count>
                        @endif
                    </x-capell-blog::tag>
                </li>
            @endforeach
        </ul>
    @endif
    @if (method_exists($tags, 'total') && $tags->hasPages())
        <x-capell::pagination
            :results="$tags"
            :scrollToWidget="$containerKey . '-' . $widget->key . '-' . $loop->index"
        />
    @endif
</x-capell-theme-foundation::widget.wrapper>
