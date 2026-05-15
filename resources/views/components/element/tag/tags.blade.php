@php
    use Capell\Blog\Support\Loader\TagLoader;
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
    'showPageContent' => $elementData['meta']['show_page_content'] ?? false,
    'showPageTitle' => $elementData['meta']['show_page_title'] ?? false,
    'element',
])
@php
    $tagPage ??= null;
    $tags ??= collect();

    if ($tags->isEmpty()) {
        $tags = TagLoader::getTags(
            site: $site,
            language: $language,
            limit: $element->meta['limit'] ?? null,
            hasArticles: true,
        );
    }

    $tagPage ??= TagLoader::getTagResultsPage($site, $language);
@endphp

<x-capell-layout-builder::element.wrapper
    class="element-tags"
    :$container
    :$containerKey
    :$containerWidth
    :$containerWidth
    :index="$loop->index"
    :$element
>
    @php
        $showTitle = $element->getMeta("container_options.{$containerKey}.hide_title") !== true
            && ($element->translation?->title || ($showPageTitle && $page->translation->title));
        $showContent = $element->getMeta("container_options.{$containerKey}.hide_content") !== true
            && ($element->translation?->content || ($showPageContent && $page->translation->content));
    @endphp

    @if ($showTitle || $showContent)
        <x-capell::content
            class="mb-6 mt-10"
            :compact="true"
            :content="$showContent ? ($element->translation->content ?: ($showPageContent ? $page->translation->content : null)) : null"
            :content-type="$element->translation->content ? $element->type->content_structure : ($showPageContent ? $page->type->content_structure : null)"
            :divider="$element->getMeta('content_divider')"
            :muted="in_array($containerKey, $theme->secondary_containers)"
            :text-align="$element->getMeta('align')"
            :title="$showTitle ? ($element->translation->title ?: ($showPageTitle ? $page->translation->title : null)) : null"
            :heading-style="$element->getMeta('heading_style')"
            :heading-tag="$showPageTitle ? 'h1' : null"
        />
    @endif

    @if ($tags->isEmpty())
        <x-capell::no-results>
            {{ $element->translation->getMeta('no_results', __('capell-blog::messages.no_tags_found')) }}
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
            :scrollToElement="$containerKey . '-' . $element->key . '-' . $loop->index"
        />
    @endif
</x-capell-layout-builder::element.wrapper>
