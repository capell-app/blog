<?php

declare(strict_types=1);

namespace Capell\Blog\Actions;

use Capell\Blog\Enums\BlogLayoutEnum;
use Capell\Blog\Enums\BlogPageTypeEnum;
use Capell\Blog\Models\Article;
use Capell\Core\Enums\BlueprintSubjectEnum;
use Capell\Core\Models\Blueprint;
use Capell\Core\Models\Language;
use Capell\Core\Models\Layout;
use Capell\Core\Models\SiteDomain;
use Capell\ScreenshotFixtures\FixtureState;
use Capell\Tags\Models\Tag;
use Carbon\CarbonImmutable;
use RuntimeException;

/**
 * Seeds a small set of published articles for disposable screenshot runs so the
 * article admin index and public blog listing are captured populated.
 */
final class SeedBlogScreenshotFixtureAction
{
    private const string FixtureMarker = 'blog-screenshot';

    /**
     * @var list<array{name: string, published: string, tag: string, content: string}>
     */
    private const array Articles = [
        ['name' => 'Planning a content calendar that survives launch week', 'published' => '-70 days', 'tag' => 'Planning', 'content' => '<p>Launch week compresses every deadline. Start the calendar six weeks out, give each piece an owner and a reviewer, and keep two slots free for the things that always arrive late. A calendar only works when it is visible: share it with support and sales so nobody is surprised by what goes live.</p>'],
        ['name' => 'How we reorganised our help centre around real questions', 'published' => '-56 days', 'tag' => 'Support', 'content' => '<p>We read three months of support conversations and grouped them by the question the customer actually asked, not by our product areas. The new help centre has fewer sections, clearer titles and a search box that learns from the questions people type.</p>'],
        ['name' => 'Five editorial checks before you press publish', 'published' => '-42 days', 'tag' => 'Editorial', 'content' => '<p>Read the headline aloud, check every link, confirm the date and author, look at the page on a phone and ask whether the first paragraph stands alone. These five checks take under ten minutes and catch most of the mistakes readers notice.</p>'],
        ['name' => 'Writing product updates customers actually read', 'published' => '-28 days', 'tag' => 'Editorial', 'content' => '<p>Lead with what changed for the reader, show it in one screenshot and say what to do next. Keep the technical detail for the changelog. Short, regular updates build more trust than a quarterly essay.</p>'],
    ];

    public static function run(): int
    {
        self::assertDisposableScreenshotEnvironment();

        require_once (str_contains(__DIR__, '/vendor/capell-app/') ? dirname(__DIR__, 5) : dirname(__DIR__, 4)) . '/scripts/screenshots/FixtureState.php';
        FixtureState::boot();

        $site = FixtureState::selectedSite();

        return (new Article)->getConnection()->transaction(static function () use ($site): int {
            $language = $site->language;
            throw_unless($language instanceof Language, RuntimeException::class, 'Blog screenshot fixtures require a site language.');
            $existingArticles = Article::withTrashed()->lockForUpdate()->where('site_id', $site->getKey())
                ->whereIn('name', array_column(self::Articles, 'name'))->get();
            foreach ($existingArticles as $existingArticle) {
                throw_unless(
                    ! $existingArticle->trashed() && data_get($existingArticle->meta, 'screenshot_fixture') === self::FixtureMarker,
                    RuntimeException::class,
                    'A Blog screenshot article name is already in use.',
                );
            }

            if (! SiteDomain::query()->where('site_id', $site->getKey())->exists()) {
                SiteDomain::factory()->site($site)->language($language)->default()->create();
            }

            // Installation owns shared publishing defaults. Capture must only read them;
            // the generic article factory otherwise repairs widgets and layouts.
            $layout = Layout::query()->where('key', BlogLayoutEnum::Article->value)->first();
            $type = Blueprint::query()->where('key', BlogPageTypeEnum::Article->value)
                ->where('type', BlueprintSubjectEnum::Page->value)->first();
            throw_unless($layout instanceof Layout && $type instanceof Blueprint, RuntimeException::class, 'Install Blog publishing defaults before seeding screenshot articles.');

            foreach (self::Articles as $row) {
                $article = Article::query()
                    ->where('site_id', $site->getKey())
                    ->where('name', $row['name'])
                    ->first();
                $publishedAt = CarbonImmutable::parse($row['published'], 'UTC')->startOfDay();

                if (! $article instanceof Article) {
                    $article = Article::factory()
                        ->site($site)
                        ->layout($layout)
                        ->type($type)
                        ->withTranslations(data: ['title' => $row['name']])
                        ->withExampleImages()
                        ->create(['name' => $row['name'], 'meta' => ['screenshot_fixture' => self::FixtureMarker]]);
                }

                // Repair earlier fixture rows too: future dates and expired windows
                // otherwise leave a populated admin table with an empty public blog.
                foreach ($article->translations()->get() as $translation) {
                    $translation->content = $row['content'];
                    $translation->meta = [...($translation->meta ?? []), 'summary' => strip_tags($row['content'])];
                    $translation->save();
                }

                // Article's general tag method resolves names across all sites. Bind this
                // disposable fixture to a tag scoped to its own site before syncing.
                $tag = Tag::findOrCreateForSite($row['tag'], 'page', $language->code, $site->id);
                $article->syncTagsWithType([$tag], 'page');

                $article->forceFill([
                    'visible_from' => $publishedAt,
                    'visible_until' => null,
                    'created_at' => $publishedAt,
                ])->save();
            }

            return Article::query()
                ->where('site_id', $site->getKey())
                ->whereIn('name', array_column(self::Articles, 'name'))
                ->count();
        });
    }

    private static function assertDisposableScreenshotEnvironment(): void
    {
        $configuredAppPath = getenv('CAPELL_SCREENSHOT_APP_PATH');
        $basePath = realpath(base_path());
        $appPath = is_string($configuredAppPath) ? realpath($configuredAppPath) : false;
        $environment = app()->bound('config') ? config('app.env') : getenv('APP_ENV');

        throw_unless(
            in_array($environment, ['local', 'testing'], true)
                && in_array(getenv('CAPELL_SCREENSHOT_FIXTURE'), ['1', 'true', 'record-state'], true)
                && is_string($basePath)
                && is_string($appPath)
                && $basePath === $appPath
                && ($basePath === realpath((str_contains(__DIR__, '/vendor/capell-app/') ? dirname(__DIR__, 5) : dirname(__DIR__, 4)) . '/vendor/orchestra/testbench-core/laravel')
                    || str_starts_with($basePath, (str_contains(__DIR__, '/vendor/capell-app/') ? dirname(__DIR__, 5) : dirname(__DIR__, 4)) . '/tmp/testbench-skeletons/')),
            RuntimeException::class,
            'Blog screenshot fixtures require the explicit disposable local screenshot environment.',
        );

        $captureEmail = getenv('CAPELL_SCREENSHOT_ADMIN_EMAIL') ?: (getenv('CAPELL_ADMIN_EMAIL') ?: 'admin@example.com');
        $authenticated = auth()->user();
        throw_if(
            $authenticated !== null && data_get($authenticated, 'email') !== $captureEmail,
            RuntimeException::class,
            'Screenshot fixtures require the selected capture actor.',
        );
    }
}
