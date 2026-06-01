@props([
    'container',
    'containerKey',
    'containerWidth' => null,
    'loop',
    'block',
])

<x-capell-foundation-theme::block.wrapper
    class="capell-tag-tags block block-{{ $block->key }} block-tags"
    :$container
    :$containerKey
    :$containerWidth
    :$containerWidth
    :index="$loop->index"
    :widget="$block"
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
        <x-capell::no-results>
            {{ $noResultsText }}
        </x-capell::no-results>
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
                            </x-slot>
                        @endif
                    </x-capell-blog::tag>
                </li>
            @endforeach
        </ul>
    @endif
    @if (method_exists($tags, 'total') && $tags->hasPages())
        <x-capell::pagination
            :results="$tags"
            :scrollToBlock="$containerKey . '-' . $block->key . '-' . $loop->index"
        />
    @endif
</x-capell-foundation-theme::block.wrapper>
