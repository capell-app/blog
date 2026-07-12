<?php

declare(strict_types=1);

namespace Capell\Blog\Support\EditorialCalendar;

use Capell\Blog\Models\Article;
use Capell\Blog\Providers\BlogServiceProvider;
use Capell\Core\Actions\GetEditPageResourceUrlAction;
use Capell\PublishingStudio\Contracts\EditorialCalendarEventContributor;
use Capell\PublishingStudio\Data\EditorialCalendarEventData;
use Capell\PublishingStudio\Data\EditorialCalendarQueryData;
use Capell\PublishingStudio\Enums\SchedulerEventStateEnum;
use Capell\PublishingStudio\Enums\SchedulerEventTypeEnum;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

final class BlogEditorialCalendarEventContributor implements EditorialCalendarEventContributor
{
    /**
     * @return Collection<int, EditorialCalendarEventData>
     */
    public function editorialCalendarEvents(EditorialCalendarQueryData $query): Collection
    {
        if (! $this->shouldContribute($query)) {
            return collect();
        }

        $events = collect();

        if ($this->includesEventType($query, SchedulerEventTypeEnum::Publish)) {
            $events = $events->merge($this->articleColumnEvents($query, 'visible_from', SchedulerEventTypeEnum::Publish));
        }

        if ($this->includesEventType($query, SchedulerEventTypeEnum::Unpublish)) {
            return $events->merge($this->articleColumnEvents($query, 'visible_until', SchedulerEventTypeEnum::Unpublish));
        }

        return $events;
    }

    private function shouldContribute(EditorialCalendarQueryData $query): bool
    {
        if ($query->sourceTypes !== null && ! in_array('article', $query->sourceTypes, true)) {
            return false;
        }

        if ($query->state !== null && $query->state !== SchedulerEventStateEnum::Scheduled->value) {
            return false;
        }

        return $query->ownerId === null && $query->ownerType === null;
    }

    private function includesEventType(EditorialCalendarQueryData $query, SchedulerEventTypeEnum $eventType): bool
    {
        return $query->eventTypes === null || in_array($eventType->value, $query->eventTypes, true);
    }

    /**
     * @return Collection<int, EditorialCalendarEventData>
     */
    private function articleColumnEvents(
        EditorialCalendarQueryData $query,
        string $column,
        SchedulerEventTypeEnum $eventType,
    ): Collection {
        return Article::query()
            ->with(['site', 'blueprint'])
            ->whereBetween($column, [$query->startsAt, $query->endsAt])
            ->when($query->siteIds !== null, fn (Builder $builder): Builder => $builder->whereIn('site_id', $query->siteIds))
            ->orderBy($column)
            ->limit($query->limit)
            ->get()
            ->map(fn (Article $article): EditorialCalendarEventData => $this->articleEvent($article, $column, $eventType));
    }

    private function articleEvent(Article $article, string $column, SchedulerEventTypeEnum $eventType): EditorialCalendarEventData
    {
        $scheduledFor = $article->getAttribute($column);

        return new EditorialCalendarEventData(
            id: 'article-' . $article->id . '-' . $eventType->value,
            sourcePackage: BlogServiceProvider::$packageName,
            sourceType: 'article',
            sourceId: (string) $article->getKey(),
            title: $article->name,
            eventType: $eventType->value,
            startsAt: $scheduledFor instanceof CarbonInterface
                ? CarbonImmutable::instance($scheduledFor)
                : CarbonImmutable::parse((string) $scheduledFor),
            eventTypeLabel: $eventType->getLabel(),
            status: (string) __('capell-blog::generic.editorial_calendar.status_scheduled'),
            description: (string) __('capell-blog::generic.editorial_calendar.descriptions.' . $eventType->value),
            recordUrl: GetEditPageResourceUrlAction::run($article),
            state: SchedulerEventStateEnum::Scheduled->value,
            siteId: is_numeric($article->site_id) ? (int) $article->site_id : null,
            siteName: $article->site?->name,
            timezone: config('app.timezone', 'UTC'),
            color: $eventType->getColor(),
        );
    }
}
