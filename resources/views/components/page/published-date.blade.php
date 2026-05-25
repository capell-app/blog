@props(['date'])
<time
    datetime="{{ $date->toW3cString() }}"
    {{ $attributes->class('published-date text-sm leading-none font-medium tracking-tight text-gray-400') }}
>
    {{ __('capell-frontend::generic.visible_from', ['date' => $date->format(config('capell-frontend.date_format'))]) }}
</time>
