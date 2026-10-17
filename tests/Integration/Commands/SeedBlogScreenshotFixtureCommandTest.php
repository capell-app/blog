<?php

declare(strict_types=1);

use Capell\Blog\Actions\SeedBlogScreenshotFixtureAction;
use Capell\Blog\Models\Article;
use Capell\Core\Models\Language;
use Capell\Core\Models\Page;
use Capell\Core\Models\Site;
use Capell\Core\Models\SiteDomain;
use Capell\LayoutBuilder\Actions\InstallPackageAction as LayoutBuilderInstallPackageAction;
use Capell\Tags\Models\Tag;
use Capell\Tests\Fixtures\Models\User as CaptureUser;
use Capell\Tests\Support\Concerns\CreatesAdminUser;
use Illuminate\Foundation\Auth\User;

uses(CreatesAdminUser::class);

beforeEach(function (): void {
    $this->createUserWithRole('super_admin', ['email' => 'admin@example.com']);
});

function withBlogScreenshotFixtureEnvironment(Closure $callback): void
{
    putenv('CAPELL_SCREENSHOT_FIXTURE=record-state');
    putenv('CAPELL_SCREENSHOT_APP_PATH=' . base_path());

    try {
        $callback();
    } finally {
        putenv('CAPELL_SCREENSHOT_FIXTURE');
        putenv('CAPELL_SCREENSHOT_APP_PATH');
    }
}

it('seeds published screenshot articles idempotently', function (): void {
    LayoutBuilderInstallPackageAction::run();
    SiteDomain::factory()->default()->create();

    withBlogScreenshotFixtureEnvironment(function (): void {
        expect(SeedBlogScreenshotFixtureAction::run())->toBe(4)
            ->and(SeedBlogScreenshotFixtureAction::run())->toBe(4);
    });
});

it('keeps same-named tags on other sites untouched and attaches only the fixture site tag', function (): void {
    LayoutBuilderInstallPackageAction::run();
    SiteDomain::factory()->default()->create();
    $fixtureSite = Site::query()->orderBy('id')->firstOrFail();
    $foreignSite = Site::factory()->withTranslations()->create();
    SiteDomain::factory()->site($foreignSite)->language($foreignSite->language)->create();
    $language = $fixtureSite->language;
    throw_unless($language instanceof Language, RuntimeException::class);
    $locale = $language->code;
    $foreignTag = Tag::findOrCreateForSite('Planning', 'page', $locale, (int) $foreignSite->getKey());
    throw_unless($foreignTag instanceof Tag, RuntimeException::class);
    $foreignPageCount = Page::query()->where('site_id', $foreignSite->getKey())->count();

    withBlogScreenshotFixtureEnvironment(function () use ($fixtureSite, $foreignSite, $foreignTag, $foreignPageCount, $locale): void {
        expect(SeedBlogScreenshotFixtureAction::run())->toBe(4);
        $article = Article::query()->where('site_id', $fixtureSite->getKey())
            ->where('name', 'Planning a content calendar that survives launch week')->firstOrFail();
        $fixtureTag = Tag::query()->where('site_id', $fixtureSite->id)->get()
            ->first(static fn (Tag $tag): bool => $tag->getTranslation('name', $locale) === 'Planning');
        throw_unless($fixtureTag instanceof Tag, RuntimeException::class);

        expect($foreignTag->fresh()?->site_id)->toBe($foreignSite->getKey())
            ->and(Page::query()->where('site_id', $foreignSite->getKey())->count())->toBe($foreignPageCount)
            ->and($fixtureTag->site_id)->toBe($fixtureSite->getKey())
            ->and($article->tags->modelKeys())->toContain($fixtureTag->getKey())
            ->not->toContain($foreignTag->getKey());
    });
});

it('refuses a same-site authored article with a fixture title before changing it', function (): void {
    LayoutBuilderInstallPackageAction::run();
    SiteDomain::factory()->default()->create();
    $site = Site::query()->orderBy('id')->firstOrFail();
    $article = Article::factory()->site($site)->withTranslations(data: [
        'title' => 'Planning a content calendar that survives launch week',
        'content' => '<p>Author-owned content</p>',
    ])->create(['name' => 'Planning a content calendar that survives launch week']);
    $original = $article->fresh()?->getRawOriginal();
    $translation = $article->translations()->firstOrFail();
    $originalTranslation = $translation->getRawOriginal();

    withBlogScreenshotFixtureEnvironment(function (): void {
        expect(fn (): int => SeedBlogScreenshotFixtureAction::run())
            ->toThrow(RuntimeException::class, 'already in use');
    });

    expect($article->fresh()?->getRawOriginal())->toBe($original)
        ->and($translation->fresh()?->getRawOriginal())->toBe($originalTranslation)
        ->and(Article::query()->where('site_id', $site->getKey())->count())->toBe(1);
});

it('refuses to seed outside the disposable screenshot environment', function (): void {
    expect(fn (): int => SeedBlogScreenshotFixtureAction::run())
        ->toThrow(RuntimeException::class, 'explicit disposable local screenshot environment');
});

it('requires --force on the blog screenshot fixture command', function (): void {
    $this->artisan('capell:blog-screenshot-fixture')->assertFailed();
});

it('refuses an authenticated actor other than the selected capture identity before writes', function (): void {
    $previous = [];
    foreach (['CAPELL_SCREENSHOT_FIXTURE', 'CAPELL_SCREENSHOT_APP_PATH', 'CAPELL_SCREENSHOT_ADMIN_EMAIL'] as $key) {
        $previous[$key] = getenv($key);
    }

    putenv('CAPELL_SCREENSHOT_FIXTURE=record-state');
    putenv('CAPELL_SCREENSHOT_APP_PATH=' . base_path());
    putenv('CAPELL_SCREENSHOT_ADMIN_EMAIL=selected-capture@example.test');
    $actor = new User;
    $actor->forceFill(['id' => 987654, 'email' => 'another-actor@example.test']);
    $this->actingAs($actor);
    $before = Article::query()->count();

    try {
        expect(fn (): int => SeedBlogScreenshotFixtureAction::run())->toThrow(RuntimeException::class, 'selected capture actor');
        expect(Article::query()->count())->toBe($before);
    } finally {
        foreach ($previous as $key => $value) {
            putenv($value === false ? $key : $key . '=' . $value);
        }
    }
});

it('rejects a different physical checkout and production even when fixture mode is selected', function (): void {
    $previous = [getenv('CAPELL_SCREENSHOT_FIXTURE'), getenv('CAPELL_SCREENSHOT_APP_PATH')];
    $environment = config('app.env');
    $runtimeEnvironment = app()->environment();
    $actor = new User;
    $before = Article::query()->count();
    putenv('CAPELL_SCREENSHOT_FIXTURE=record-state');
    try {
        foreach (['foreign-checkout', 'production'] as $state) {
            putenv('CAPELL_SCREENSHOT_APP_PATH=' . ($state === 'foreign-checkout' ? __DIR__ : base_path()));
            config()->set('app.env', $state === 'production' ? 'production' : 'testing');
            app()->instance('env', $state === 'production' ? 'production' : 'testing');
            expect(fn (): int => SeedBlogScreenshotFixtureAction::run())->toThrow(RuntimeException::class, 'explicit disposable local screenshot environment');
            expect(Article::query()->count())->toBe($before);
        }
    } finally {
        config()->set('app.env', $environment);
        app()->instance('env', $runtimeEnvironment);
        foreach (['CAPELL_SCREENSHOT_FIXTURE', 'CAPELL_SCREENSHOT_APP_PATH'] as $index => $key) {
            putenv($previous[$index] === false ? $key : $key . '=' . $previous[$index]);
        }
    }
});

it('refuses the selected actor when the fixture site is outside their site access', function (): void {
    SiteDomain::factory()->default()->create();
    $email = getenv('CAPELL_SCREENSHOT_ADMIN_EMAIL');
    $fixture = getenv('CAPELL_SCREENSHOT_FIXTURE');
    $path = getenv('CAPELL_SCREENSHOT_APP_PATH');
    putenv('CAPELL_SCREENSHOT_ADMIN_EMAIL=limited-capture@example.test');
    putenv('CAPELL_SCREENSHOT_FIXTURE=record-state');
    putenv('CAPELL_SCREENSHOT_APP_PATH=' . base_path());
    $actor = new User;
    $actor->forceFill(['id' => 987654, 'email' => 'limited-capture@example.test']);
    $this->actingAs($actor);
    try {
        expect(fn (): int => SeedBlogScreenshotFixtureAction::run())->toThrow(RuntimeException::class, 'foreign site');
    } finally {
        putenv($email === false ? 'CAPELL_SCREENSHOT_ADMIN_EMAIL' : 'CAPELL_SCREENSHOT_ADMIN_EMAIL=' . $email);
        putenv($fixture === false ? 'CAPELL_SCREENSHOT_FIXTURE' : 'CAPELL_SCREENSHOT_FIXTURE=' . $fixture);
        putenv($path === false ? 'CAPELL_SCREENSHOT_APP_PATH' : 'CAPELL_SCREENSHOT_APP_PATH=' . $path);
    }
});

it('checks the unauthenticated CLI capture actor before touching a foreign site', function (): void {
    LayoutBuilderInstallPackageAction::run();
    SiteDomain::factory()->default()->create();
    $actor = CaptureUser::query()->where('email', 'admin@example.com')->sole();
    $actor->syncRoles([]);

    auth()->logout();
    withBlogScreenshotFixtureEnvironment(function (): void {
        expect(fn (): int => SeedBlogScreenshotFixtureAction::run())->toThrow(RuntimeException::class, 'foreign site');
        expect(Article::query()->count())->toBe(0);
    });
});

it('uses the explicitly selected site on the unauthenticated CLI path', function (): void {
    LayoutBuilderInstallPackageAction::run();
    SiteDomain::factory()->default()->create();
    $first = Site::query()->orderBy('id')->firstOrFail();
    $selected = Site::factory()->withTranslations()->create();
    $previous = getenv('CAPELL_SCREENSHOT_SITE_ID');
    putenv('CAPELL_SCREENSHOT_SITE_ID=' . $selected->getKey());
    auth()->logout();
    try {
        withBlogScreenshotFixtureEnvironment(function () use ($first, $selected): void {
            expect(SeedBlogScreenshotFixtureAction::run())->toBe(4)
                ->and(Article::query()->where('site_id', $selected->getKey())->count())->toBe(4)
                ->and(Article::query()->where('site_id', $first->getKey())->count())->toBe(0);
        });
    } finally {
        putenv($previous === false ? 'CAPELL_SCREENSHOT_SITE_ID' : 'CAPELL_SCREENSHOT_SITE_ID=' . $previous);
    }
});
