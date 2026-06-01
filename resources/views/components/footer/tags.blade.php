@props([
    'headingClass' => null,
    'linkClass' => 'focus:bg-primary inline-flex items-center rounded-full bg-gray-600/75 px-3 py-2 text-sm leading-none font-medium tracking-wide text-[var(--color-footer)] no-underline hover:text-gray-400 focus:text-white',
])

<div {{ $attributes->class(['footer-tags xl:w-[20%]']) }}>
    <div class="{{ $headingClass }} mb-4">
        {{ __('Tags') }}
    </div>

    @if ($tagLinks !== [])
        <div class="flex flex-wrap gap-2">
            @foreach ($tagLinks as $tagLink)
                <x-capell-blog::tag
                    :url="$tagLink->url"
                    wire:navigate
                    color="dark"
                    size="xs"
                    class="footer-tag"
                >
                    {{ $tagLink->name }}
                    @if ($tagLink->count !== null)
                        <x-slot:count>
                            ({{ $tagLink->count }})
                        </x-slot>
                    @endif
                </x-capell-blog::tag>
            @endforeach
        </div>
    @endif
</div>
