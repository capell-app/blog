<?php

declare(strict_types=1);

use Capell\AccessGate\Actions\SeedAccessGateScreenshotFixtureAction;
use Capell\Address\Actions\SeedAddressScreenshotFixtureAction;
use Capell\AgentBridge\Actions\Fixtures\PrepareAgentBridgeScreenshotAction;
use Capell\AiCreator\Actions\SeedAiCreatorScreenshotFixtureAction;
use Capell\AutomationStudio\Actions\SeedAutomationStudioScreenshotFixtureAction;
use Capell\Blog\Actions\EnsureArticlePublishingDefaultsAction;
use Capell\Blog\Actions\SeedBlogScreenshotFixtureAction;
use Capell\Blog\Models\Article;
use Capell\Bookings\Actions\SeedBookingsScreenshotFixtureAction;
use Capell\CampaignStudio\Actions\SeedCampaignStudioScreenshotFixtureAction;
use Capell\Contacts\Actions\SeedContactsScreenshotFixtureAction;
use Capell\Core\Models\Language;
use Capell\Core\Models\Page;
use Capell\Core\Models\Site;
use Capell\Core\Models\SiteDomain;
use Capell\CustomerPortal\Actions\SeedCustomerPortalScreenshotFixtureAction;
use Capell\Deployments\Actions\SeedDeploymentsScreenshotFixtureAction;
use Capell\EmailStudio\Actions\SeedEmailStudioScreenshotFixtureAction;
use Capell\Experiments\Actions\SeedExperimentsScreenshotFixtureAction;
use Capell\FormBuilder\Actions\SeedFormBuilderScreenshotFixtureAction;
use Capell\KnowledgeBase\Actions\SeedKnowledgeBaseScreenshotFixtureAction;
use Capell\LayoutBuilder\Actions\InstallPackageAction as LayoutBuilderInstallPackageAction;
use Capell\LayoutBuilder\Actions\SeedLayoutBuilderScreenshotFixtureAction;
use Capell\LayoutBuilder\Actions\SeedWidgetIntegrityScreenshotFixturesAction;
use Capell\LiveChat\Actions\SeedLiveChatScreenshotFixtureAction;
use Capell\Navigation\Actions\SeedNavigationScreenshotFixtureAction;
use Capell\Navigation\Models\Navigation;
use Capell\Newsletter\Actions\SeedNewsletterScreenshotFixtureAction;
use Capell\Notes\Actions\PrepareEmptyNotesScreenshotInboxAction;
use Capell\PrivacyCenter\Actions\SeedPrivacyCenterScreenshotFixtureAction;
use Capell\PublishingStudio\Actions\PreparePublishingWorkflowScreenshotAction;
use Capell\RecordSwitcher\Actions\Fixtures\PrepareRecordSwitcherScreenshotAction;
use Capell\Referer\Actions\SeedRefererScreenshotFixtureAction;
use Capell\Search\Actions\SeedSearchScreenshotFixtureAction;
use Capell\Search\Actions\SeedSearchScreenshotWidgetAction;
use Capell\ShopifyCommerce\Actions\PrepareShopifyScreenshotStateAction;
use Capell\SiteMonitor\Actions\Fixtures\PrepareSiteMonitorScreenshotAction;
use Capell\SocialFeeds\Actions\SeedSocialFeedsScreenshotFixtureAction;
use Capell\Socials\Actions\SeedSocialsScreenshotFixtureAction;
use Capell\StructuredContentLibrary\Actions\SeedStructuredContentScreenshotFixtureAction;
use Capell\Tags\Models\Tag;
use Capell\Tests\Fixtures\Models\User as CaptureUser;
use Capell\Tests\Support\Concerns\CreatesAdminUser;
use Capell\Tests\Support\OwnedTestbenchSkeleton;
use Capell\UrlManager\Actions\PrepareSavedRuleScreenshotAction;
use Capell\WelcomeTour\Actions\PrepareWelcomeTourScreenshotAction;
use Illuminate\Foundation\Auth\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Workbench\App\Actions\AssertScreenshotFixtureEnvironmentAction;
use Workbench\App\Actions\PrepareEventsScreenshotsAction;
use Workbench\App\Actions\PreparePublishingScreenshotAction;
use Workbench\App\Actions\PrepareWidgetScreenshotsAction;

uses(CreatesAdminUser::class);

beforeEach(function (): void {
    $this->createUserWithRole('super_admin', ['email' => 'admin@example.com']);
    LayoutBuilderInstallPackageAction::run();
    EnsureArticlePublishingDefaultsAction::run();
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

it('refuses a real local application even when its physical path and fixture flags match', function (string $class, string $method): void {
    $basePath = app()->basePath();
    $environment = config('app.env');
    $root = sys_get_temp_dir() . '/capell-real-local-app-' . bin2hex(random_bytes(8));
    mkdir($root);
    config()->set('app.env', 'local');
    app()->setBasePath($root);
    try {
        withBlogScreenshotFixtureEnvironment(function () use ($class, $method): void {
            expect(fn (): mixed => new ReflectionMethod($class, $method)->invoke(null))
                ->toThrow(RuntimeException::class, 'explicit disposable local screenshot environment');
        });
    } finally {
        app()->setBasePath($basePath);
        config()->set('app.env', $environment);
        rmdir($root);
    }
})->with('screenshot fixture guards');

dataset('screenshot fixture guards', [
    'automation-studio' => [SeedAutomationStudioScreenshotFixtureAction::class, 'assertDisposableScreenshotEnvironment'],
    'email-studio' => [SeedEmailStudioScreenshotFixtureAction::class, 'assertDisposableScreenshotEnvironment'],
    'bookings' => [SeedBookingsScreenshotFixtureAction::class, 'assertDisposableScreenshotEnvironment'],
    'layout-builder' => [SeedLayoutBuilderScreenshotFixtureAction::class, 'assertDisposableScreenshotEnvironment'],
    'structured-content-library' => [SeedStructuredContentScreenshotFixtureAction::class, 'assertDisposableScreenshotEnvironment'],
    'experiments' => [SeedExperimentsScreenshotFixtureAction::class, 'assertDisposableScreenshotEnvironment'],
    'contacts' => [SeedContactsScreenshotFixtureAction::class, 'assertDisposableScreenshotEnvironment'],
    'social-feeds' => [SeedSocialFeedsScreenshotFixtureAction::class, 'assertDisposableScreenshotEnvironment'],
    'blog' => [SeedBlogScreenshotFixtureAction::class, 'assertDisposableScreenshotEnvironment'],
    'campaign-studio' => [SeedCampaignStudioScreenshotFixtureAction::class, 'assertDisposableScreenshotEnvironment'],
    'access-gate' => [SeedAccessGateScreenshotFixtureAction::class, 'assertDisposableScreenshotEnvironment'],
    'knowledge-base' => [SeedKnowledgeBaseScreenshotFixtureAction::class, 'assertDisposableScreenshotEnvironment'],
    'form-builder' => [SeedFormBuilderScreenshotFixtureAction::class, 'assertDisposableScreenshotEnvironment'],
    'address' => [SeedAddressScreenshotFixtureAction::class, 'assertDisposableScreenshotEnvironment'],
    'ai-creator' => [SeedAiCreatorScreenshotFixtureAction::class, 'assertDisposableScreenshotEnvironment'],
    'socials' => [SeedSocialsScreenshotFixtureAction::class, 'assertDisposableScreenshotEnvironment'],
    'newsletter' => [SeedNewsletterScreenshotFixtureAction::class, 'assertDisposableScreenshotEnvironment'],
    'customer-portal' => [SeedCustomerPortalScreenshotFixtureAction::class, 'assertDisposableScreenshotEnvironment'],
    'privacy-center' => [SeedPrivacyCenterScreenshotFixtureAction::class, 'assertDisposableScreenshotEnvironment'],
    'deployments' => [SeedDeploymentsScreenshotFixtureAction::class, 'assertDisposableScreenshotEnvironment'],
    'live-chat' => [SeedLiveChatScreenshotFixtureAction::class, 'assertDisposableScreenshotEnvironment'],
]);

it('leaves shared widgets layouts pages and navigation byte-for-byte unchanged', function (): void {
    LayoutBuilderInstallPackageAction::run();
    SiteDomain::factory()->default()->create();
    $site = Site::query()->orderBy('id')->firstOrFail();
    $language = $site->language;
    throw_unless($language instanceof Language, RuntimeException::class, 'Expected the capture site language.');
    Navigation::factory()->site($site)->language($language)->create(['key' => 'main']);
    $tables = ['widgets', 'layouts', 'pages', 'navigations'];
    $snapshot = [];
    foreach ($tables as $table) {
        $snapshot[$table] = DB::table($table)->orderBy('id')->get()->toJson();
    }

    withBlogScreenshotFixtureEnvironment(function () use ($tables, $snapshot): void {
        expect(SeedBlogScreenshotFixtureAction::run())->toBe(4);
        expect(SeedBlogScreenshotFixtureAction::run())->toBe(4);
        foreach ($tables as $table) {
            expect(DB::table($table)->orderBy('id')->get()->toJson())->toBe($snapshot[$table], $table);
        }
    });
});

it('rejects real local apps through the remaining fixture entry points', function (Closure $fixture, bool $http, string $message = 'disposable'): void {
    $basePath = app()->basePath();
    $environment = config('app.env');
    $runtimeEnvironment = app()->environment();
    $root = sys_get_temp_dir() . '/capell-real-app-entry-' . bin2hex(random_bytes(8));
    mkdir($root);
    $this->actingAs(CaptureUser::query()->where('email', 'admin@example.com')->sole());
    config()->set('app.env', 'local');
    app()->instance('env', 'local');
    app()->setBasePath($root);
    try {
        withBlogScreenshotFixtureEnvironment(function () use ($fixture, $http, $message): void {
            if ($http) {
                expect($fixture)->toThrow(HttpException::class);
            } else {
                expect($fixture)->toThrow(RuntimeException::class, $message);
            }
        });
    } finally {
        app()->setBasePath($basePath);
        config()->set('app.env', $environment);
        app()->instance('env', $runtimeEnvironment);
        rmdir($root);
    }
})->with([
    'search-widgets' => [fn () => resolve(SeedSearchScreenshotWidgetAction::class)->handle(), false],
    'widget-integrity' => [fn () => SeedWidgetIntegrityScreenshotFixturesAction::run(), false],
    'search' => [fn () => resolve(SeedSearchScreenshotFixtureAction::class)->handle(), false],
    'navigation' => [fn () => resolve(SeedNavigationScreenshotFixtureAction::class)->handle(), false],
    'agent-bridge' => [fn () => resolve(PrepareAgentBridgeScreenshotAction::class)->handle(auth()->user() ?? throw new RuntimeException('Expected the capture actor.')), false],
    'site-monitor' => [fn () => resolve(PrepareSiteMonitorScreenshotAction::class)->handle(), false],
    'record-switcher' => [fn () => resolve(PrepareRecordSwitcherScreenshotAction::class)->seedForCapture(), true],
    'notes' => [fn () => resolve(PrepareEmptyNotesScreenshotInboxAction::class)->handle(), false],
    'publishing' => [fn () => resolve(PreparePublishingWorkflowScreenshotAction::class)->handle('dashboard'), true],
    'referer' => [fn () => SeedRefererScreenshotFixtureAction::run(), false, 'Referer screenshot fixtures require the explicit disposable local screenshot environment.'],
    'shopify' => [fn () => resolve(PrepareShopifyScreenshotStateAction::class)->handle('products'), false],
    'url-manager' => [fn () => resolve(PrepareSavedRuleScreenshotAction::class)->handle(), true],
    'welcome-tour' => [fn () => resolve(PrepareWelcomeTourScreenshotAction::class)->handle('dashboard'), true],
    'workbench-widgets' => [fn () => resolve(PrepareWidgetScreenshotsAction::class)->handle(), false],
    'workbench-publishing' => [fn () => resolve(PreparePublishingScreenshotAction::class)->handle(), false],
    'workbench-events' => [fn () => resolve(PrepareEventsScreenshotsAction::class)->handle(), false],
    'admin' => [fn () => resolve(AssertScreenshotFixtureEnvironmentAction::class)->handle(), false],
]);

it('accepts the process-owned disposable Testbench copy', function (string $class, string $method): void {
    $basePath = app()->basePath();
    app()->setBasePath(OwnedTestbenchSkeleton::path());
    try {
        withBlogScreenshotFixtureEnvironment(function () use ($class, $method): void {
            expect(new ReflectionMethod($class, $method)->invoke(null))->toBeNull();
        });
    } finally {
        app()->setBasePath($basePath);
    }
})->with('screenshot fixture guards');

it('refuses an ambiguous default Blog site instead of choosing the first accessible row', function (): void {
    Site::factory()->withTranslations()->count(2)->create(['default' => true]);
    Site::query()->update(['default' => true]);
    expect(Site::query()->where('default', true)->count())->toBe(2);
    withBlogScreenshotFixtureEnvironment(function (): void {
        expect(fn (): int => SeedBlogScreenshotFixtureAction::run())->toThrow(RuntimeException::class)
            ->and(Article::query()->count())->toBe(0);
    });
});

it('records creation receipts for fixture articles without adopting a foreign article', function (): void {
    $foreign = Article::factory()->create(['name' => 'An author article']);
    withBlogScreenshotFixtureEnvironment(function () use ($foreign): void {
        SeedBlogScreenshotFixtureAction::run();
        expect(Schema::hasTable('screenshot_fixture_receipts'))->toBeTrue();
        $receipts = DB::table('screenshot_fixture_receipts')->where('table_name', $foreign->getTable())->get();
        expect($receipts)->toHaveCount(4);
        foreach ($receipts as $receipt) {
            $identity = json_decode($receipt->identity_json, true, flags: JSON_THROW_ON_ERROR);
            throw_unless(is_array($identity) && is_int($identity['id'] ?? null), RuntimeException::class, 'Expected an integer receipt key.');
            expect($identity['id'])->not->toBe($foreign->getKey())
                ->and($receipt->owner)->toBe('packages/blog/src/Actions/SeedBlogScreenshotFixtureAction.php');
        }
    });
});

it('rejects an invalid explicit site selector before preparing a theme page', function (string $helper): void {
    require dirname(__DIR__, 5) . '/workbench/routes/screenshot-fixtures.php';
    throw_unless(is_callable($helper), RuntimeException::class, 'Expected a theme fixture helper.');
    Site::factory()->withTranslations()->create(['default' => true]);
    $previous = getenv('CAPELL_SCREENSHOT_SITE_ID');
    putenv('CAPELL_SCREENSHOT_SITE_ID=invalid');
    $before = Page::query()->count();
    try {
        withBlogScreenshotFixtureEnvironment(function () use ($helper, $before): void {
            expect(fn () => $helper('editorial', 'empty'))->toThrow(RuntimeException::class, 'valid selected site')
                ->and(Page::query()->count())->toBe($before);
        });
    } finally {
        putenv($previous === false ? 'CAPELL_SCREENSHOT_SITE_ID' : 'CAPELL_SCREENSHOT_SITE_ID=' . $previous);
    }
})->with(['screenshotFixtureBlogPageWithLock', 'screenshotFixtureSearchPageWithLock']);
