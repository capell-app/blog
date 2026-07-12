<x-filament-widgets::widget>
    <x-filament::section>
        <x-slot name="heading">
            {{ __('capell-blog::generic.admin_widgets.site_traffic_heading') }}
        </x-slot>

        <div class="space-y-4">
            <div class="flex gap-6 text-sm">
                <span class="font-medium text-gray-700 dark:text-gray-300">
                    {{ __('capell-blog::generic.admin_widgets.views_count', ['count' => number_format($data->totalViews)]) }}
                </span>
                <span class="font-medium text-gray-500 dark:text-gray-400">
                    {{ __('capell-blog::generic.admin_widgets.visitors_count', ['count' => number_format($data->totalVisitors)]) }}
                </span>
            </div>

            @if ($data->points->isEmpty())
                <p class="text-sm text-gray-500">
                    {{ __('capell-blog::generic.admin_widgets.no_traffic_data') }}
                </p>
            @else
                <div class="flex h-32 items-end gap-1">
                    @foreach ($data->points as $point)
                        @php
                            $maxViews = $data->points->max('views') ?: 1;
                            $viewsHeight = round(($point->views / $maxViews) * 100);
                            $visitorsHeight = round(($point->visitors / $maxViews) * 100);
                        @endphp

                        <div
                            class="flex flex-1 flex-col items-center gap-0.5"
                            title="{{ __('capell-blog::generic.admin_widgets.traffic_point_title', ['date' => $point->date, 'views' => $point->views, 'visitors' => $point->visitors]) }}"
                        >
                            <div class="flex h-28 w-full items-end gap-px">
                                <div
                                    class="flex-1 rounded-t bg-amber-400 dark:bg-amber-500"
                                    style="height: {{ $viewsHeight }}%"
                                ></div>
                                <div
                                    class="flex-1 rounded-t bg-blue-400 dark:bg-blue-500"
                                    style="height: {{ $visitorsHeight }}%"
                                ></div>
                            </div>
                        </div>
                    @endforeach
                </div>
                <div class="flex gap-4 text-xs text-gray-500">
                    <span class="flex items-center gap-1.5">
                        <span
                            class="inline-block h-3 w-3 rounded-sm bg-amber-400"
                        ></span>
                        {{ __('capell-blog::generic.admin_widgets.views') }}
                    </span>
                    <span class="flex items-center gap-1.5">
                        <span
                            class="inline-block h-3 w-3 rounded-sm bg-blue-400"
                        ></span>
                        {{ __('capell-blog::generic.admin_widgets.visitors') }}
                    </span>
                </div>
            @endif
        </div>
    </x-filament::section>
</x-filament-widgets::widget>
