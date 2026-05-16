<?php
use Capell\Frontend\Facades\Frontend;

$page = Frontend::page();
$theme = Frontend::theme();

?>

@props([
    'author',
])
@php
    use Illuminate\Database\Eloquent\Model;

    $profileImage = $author instanceof Model && $author->relationLoaded('profileImage')
        ? $author->getRelation('profileImage')
        : null;
@endphp

@if ($author)
    <div {{ $attributes->class('page-author flex items-center gap-5') }}>
        @if ($profileImage)
            <x-capell::media
                :media="$profileImage"
                fit="crop"
                :width="120"
                class="h-12 w-12 flex-shrink-0 rounded-lg bg-white object-cover object-center"
                loading="lazy"
                alt=""
                aria-hidden="true"
            />
        @else
            <span
                aria-hidden="true"
                class="bg-primary/10 text-primary flex h-12 w-12 flex-shrink-0 items-center justify-center rounded-lg text-sm font-semibold uppercase"
            >
                {{ Str::of($author->name)->trim()->substr(0, 1) }}
            </span>
        @endif

        <div class="leading-tight tracking-wide">
            <span class="text-secondary block text-sm font-semibold">
                {{ $author->name }}
            </span>

            @if ($author->bio)
                <div
                    @class([
                        'prose text-sm font-light leading-tight text-gray-500 [&>:first-child]:mt-0 [&>:last-child]:mb-0',
                        'dark:prose-invert' => $theme->withDarkMode,
                    ])
                >
                    {!! nl2br(e($author->bio)) !!}
                </div>
            @endif
        </div>
    </div>
@endif
