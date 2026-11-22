<?php

declare(strict_types=1);

use Capell\Blog\Bridges\PublishingStudio\BlogEditorialCalendarEventContributor;
use Capell\Blog\Models\Article;
use Capell\Blog\Providers\BlogServiceProvider;
use Capell\Core\Models\Site;
use Capell\PublishingStudio\Actions\BuildEditorialCalendarEventsAction;
use Capell\PublishingStudio\Contracts\EditorialCalendarEventContributor;
use Capell\PublishingStudio\Data\EditorialCalendarEventData;
use Capell\PublishingStudio\Data\EditorialCalendarQueryData;
use Capell\PublishingStudio\Enums\SchedulerEventStateEnum;
use Capell\PublishingStudio\Enums\SchedulerEventTypeEnum;
use Capell\Tests\Support\Concerns\CreatesAdminUser;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Gate;
use Illuminate\Translation\Translator;
use Spatie\Permission\Models\Permission;

uses(CreatesAdminUser::class);

beforeEach(function (): void {
    $this->actingAsAdmin();
});

afterEach(function (): void {
    CarbonImmutable::setTestNow();
});

it('contributes scheduled article publish and unpublish events to the editorial calendar', function (): void {
    __('capell-blog::generic.editorial_calendar.status_scheduled');
    resolve(Translator::class)->addLines([
        'generic.editorial_calendar.descriptions.publish' => 'The scheduled article becomes visible.',
        'generic.editorial_calendar.descriptions.unpublish' => 'The scheduled article becomes hidden.',
    ], app()->getLocale(), 'capell-blog');

    CarbonImmutable::setTestNow(CarbonImmutable::parse('2026-05-01 09:00:00', 'UTC'));

    $site = Site::factory()->withTranslations()->create(['name' => 'Primary site']);
    $otherSite = Site::factory()->withTranslations()->create(['name' => 'Other site']);

    $article = Article::factory()->site($site)->create([
        'name' => 'Product release notes',
        'visible_from' => CarbonImmutable::parse('2026-05-10 10:00:00', 'UTC'),
        'visible_until' => CarbonImmutable::parse('2026-05-20 10:00:00', 'UTC'),
    ]);

    Article::factory()->site($otherSite)->create([
        'name' => 'Filtered article',
        'visible_from' => CarbonImmutable::parse('2026-05-12 10:00:00', 'UTC'),
    ]);

    $events = (new BlogEditorialCalendarEventContributor)->editorialCalendarEvents(new EditorialCalendarQueryData(
        startsAt: CarbonImmutable::parse('2026-05-01 00:00:00', 'UTC'),
        endsAt: CarbonImmutable::parse('2026-05-31 23:59:59', 'UTC'),
        sourceTypes: ['article'],
        siteIds: [(int) $site->id],
        state: SchedulerEventStateEnum::Scheduled->value,
    ));

    $firstEvent = $events->first();

    throw_unless($firstEvent instanceof EditorialCalendarEventData, RuntimeException::class, 'Expected the blog contributor to return an editorial calendar event.');

    expect($events)->toHaveCount(2)
        ->and($events->pluck('id')->all())->toBe([
            'article-' . $article->id . '-publish',
            'article-' . $article->id . '-unpublish',
        ])
        ->and($events->pluck('sourcePackage')->unique()->values()->all())->toBe([BlogServiceProvider::$packageName])
        ->and($events->pluck('sourceType')->unique()->values()->all())->toBe(['article'])
        ->and($events->pluck('eventType')->all())->toBe([
            SchedulerEventTypeEnum::Publish->value,
            SchedulerEventTypeEnum::Unpublish->value,
        ])
        ->and($firstEvent->description)->toBe(__('capell-blog::generic.editorial_calendar.descriptions.publish'))
        ->not->toBe('capell-blog::generic.editorial_calendar.descriptions.publish')
        ->and($events->last()?->description)->toBe(__('capell-blog::generic.editorial_calendar.descriptions.unpublish'))
        ->not->toBe('capell-blog::generic.editorial_calendar.descriptions.unpublish')
        ->and($firstEvent->title)->toBe('Product release notes')
        ->and($firstEvent->siteId)->toBe((int) $site->id)
        ->and($firstEvent->siteName)->toBe('Primary site')
        ->and($firstEvent->state)->toBe(SchedulerEventStateEnum::Scheduled->value);
});

it('is included by the publishing studio editorial calendar aggregator', function (): void {
    CarbonImmutable::setTestNow(CarbonImmutable::parse('2026-05-01 09:00:00', 'UTC'));

    $site = Site::factory()->withTranslations()->create();

    Article::factory()->site($site)->create([
        'name' => 'Calendar article',
        'visible_from' => CarbonImmutable::parse('2026-05-10 10:00:00', 'UTC'),
    ]);

    $events = BuildEditorialCalendarEventsAction::run(
        startsAt: CarbonImmutable::parse('2026-05-01 00:00:00', 'UTC'),
        endsAt: CarbonImmutable::parse('2026-05-31 23:59:59', 'UTC'),
        sourceTypes: ['article'],
        eventTypes: [SchedulerEventTypeEnum::Publish->value],
        siteIds: [(int) $site->id],
        state: SchedulerEventStateEnum::Scheduled->value,
    );

    $firstEvent = $events->first();

    throw_unless($firstEvent instanceof EditorialCalendarEventData, RuntimeException::class, 'Expected the aggregator to return a blog editorial calendar event.');

    expect($events)->toHaveCount(1)
        ->and($firstEvent->sourcePackage)->toBe(BlogServiceProvider::$packageName)
        ->and($firstEvent->sourceType)->toBe('article')
        ->and($firstEvent->title)->toBe('Calendar article');
});

it('registers the blog editorial calendar contributor when publishing studio is available', function (): void {
    $contributors = collect(app()->tagged(EditorialCalendarEventContributor::TAG));

    expect($contributors->contains(fn (mixed $contributor): bool => $contributor instanceof BlogEditorialCalendarEventContributor))->toBeTrue();
});

it('requires source read permission before contributing calendar records', function (?string $grant): void {
    CarbonImmutable::setTestNow(CarbonImmutable::parse('2026-05-01 09:00:00', 'UTC'));
    Article::factory()->create(['visible_from' => CarbonImmutable::now()->addDay(), 'visible_until' => null]);

    if ($grant === 'guest') {
        auth()->forgetGuards();
    } elseif ($grant !== 'super_admin') {
        $actor = $this->createUser();
        if ($grant !== null) {
            Permission::findOrCreate($grant, 'web');
            $actor->givePermissionTo($grant);
        }

        $this->actingAs($actor);
    }

    $allowed = $grant !== null && $grant !== 'guest';
    expect(Gate::allows('viewAny', Article::class))->toBe($allowed);
    $events = (new BlogEditorialCalendarEventContributor)->editorialCalendarEvents(new EditorialCalendarQueryData(
        startsAt: CarbonImmutable::now(),
        endsAt: CarbonImmutable::now()->addWeek(),
        sourceTypes: ['article'],
    ));

    expect($events)->toHaveCount($allowed ? 1 : 0);
})->with([
    'no read permission' => null,
    'guest' => 'guest',
    'view grant' => 'View:Article',
    'view any grant' => 'ViewAny:Article',
    'global administrator' => 'super_admin',
]);
