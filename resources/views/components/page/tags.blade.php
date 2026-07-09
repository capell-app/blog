@php
    use Filament\Support\Icons\Heroicon;
@endphp

@props ([
    'linkClass' => '',
    'tagLinks' => [],
    'tagIcon' => 'heroicon-' . Heroicon::OutlinedTag->value,
    'withDarkMode' => false,
])

@if ($tagLinks !== [])
    <div {{ $attributes->merge(['class' => 'flex items-center gap-2']) }}>
        @if ($tagIcon)
            @svg ($tagIcon, 'inline-block h-6 w-6 shrink-0 text-gray-400')
        @endif

        <div class="flex flex-wrap gap-x-2 gap-y-1.5">
            @foreach ($tagLinks as $tagLink)
                <x-capell-blog::tag
                    :url="$tagLink->url"
                    :$withDarkMode
                    wire:navigate
                >
                    {{ $tagLink->name }}
                </x-capell-blog::tag>
            @endforeach
        </div>
    </div>
@endif
