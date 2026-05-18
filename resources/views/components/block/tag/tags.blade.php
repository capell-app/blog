@php
    use Capell\Frontend\Facades\Frontend;

    $language = Frontend::language();
    $site = Frontend::site();
    $theme = Frontend::theme();
    $page = Frontend::page();
@endphp

@props([
    'container',
    'containerKey',
    'containerWidth' => null,
    'loop',
    'showPageContent' => $blockData['meta']['show_page_content'] ?? false,
    'showPageTitle' => $blockData['meta']['show_page_title'] ?? false,
    'block',
])
@php
    $tagPage ??= null;
    $tags ??= collect();
@endphp

<x-capell-foundation-theme::block.wrapper
    class="capell-tag-tags block block-{{ $block->key }} block-tags"
    :$container
    :$containerKey
    :$containerWidth
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
            class="mb-6 mt-10"
            :compact="true"
            :content="$showContent ? ($block->translation->content ?: ($showPageContent ? $page->translation->content : null)) : null"
            :content-type="$block->translation->content ? $block->type->content_structure : ($showPageContent ? $page->type->content_structure : null)"
            :divider="$block->getMeta('content_divider')"
            :muted="in_array($containerKey, $theme->secondary_containers)"
            :text-align="$block->getMeta('align')"
            :title="$showTitle ? ($block->translation->title ?: ($showPageTitle ? $page->translation->title : null)) : null"
            :heading-style="$block->getMeta('heading_style')"
            :heading-tag="$showPageTitle ? 'h1' : null"
        />
    @endif

    @if (count($tags) === 0)
        <x-capell::no-results>
            {{ $block->translation->getMeta('no_results', __('capell-blog::messages.no_tags_found')) }}
        </x-capell::no-results>
    @else
        <ul class="flex flex-wrap gap-2">
            @foreach ($tags as $tag)
                @php($url = $tag->getUrl($tagPage, $language))
                <li>
                    <x-capell-blog::tag :$url>
                        {{ $tag->getTranslation('name', $language->code) }}
                        <x-slot:count>
                            ({{ $tag->taggables_count }})
                        </x-slot>
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
