<?php

declare(strict_types=1);

?>

@php
    use Capell\Blog\Services\Loader\TagLoader;
    use Capell\Frontend\Facades\FrontendLoader;
@endphp

@props([
    'linkClass' => 'hover:text-primary focus:bg-primary inline-flex items-center rounded-full bg-gray-600/75 px-3 py-2 text-sm font-medium leading-none tracking-wide text-[var(--color-footer)] no-underline focus:text-white',
])
@php
    $language = FrontendLoader::getLanguage();
    $site = FrontendLoader::getSite();

    $tags = TagLoader::getTags($site, $language, limit: 5);

    if ($tags->isEmpty()) {
        return;
    }

    $tagPage = TagLoader::getTagResultsPage($site, $language);

    if (! $tagPage) {
        return;
    }
@endphp

<div {{ $attributes }}>
    {{ $heading ?? '' }}
    <div class="flex flex-wrap gap-2">
        @foreach ($tags as $tag)
            @php($url = $tagPage->pageUrl->full_url . '/' . $tag->getTranslation('slug', $language->code))
            <x-capell::tag
                :$url
                wire:navigate
                color-scheme="dark"
                size="sm"
            >
                {{ $tag->getTranslation('name', $language->code) }}
                ({{ $tag->pages_count }})
            </x-capell::tag>
        @endforeach
    </div>
</div>

<?php
