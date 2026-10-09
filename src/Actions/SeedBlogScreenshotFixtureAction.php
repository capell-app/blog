<?php

declare(strict_types=1);

namespace Capell\Blog\Actions;

use Capell\Blog\Models\Article;
use Capell\Core\Models\Language;
use Capell\Core\Models\Site;
use Capell\Core\Models\SiteDomain;
use Carbon\CarbonImmutable;
use RuntimeException;

/**
 * Seeds a small set of published articles for disposable screenshot runs so the
 * article admin index and public blog listing are captured populated.
 */
final class SeedBlogScreenshotFixtureAction
{
    /**
     * @var list<array{name: string, published: string}>
     */
    private const array Articles = [
        ['name' => 'Planning a content calendar that survives launch week', 'published' => '-70 days'],
        ['name' => 'How we reorganised our help centre around real questions', 'published' => '-56 days'],
        ['name' => 'Five editorial checks before you press publish', 'published' => '-42 days'],
        ['name' => 'Writing product updates customers actually read', 'published' => '-28 days'],
    ];

    public static function run(): int
    {
        self::assertDisposableScreenshotEnvironment();

        $site = Site::query()->orderBy('id')->first();

        throw_unless($site instanceof Site, RuntimeException::class, 'Blog screenshot fixtures require an installed site.');

        $language = $site->language;
        throw_unless($language instanceof Language, RuntimeException::class, 'Blog screenshot fixtures require a site language.');
        if (! SiteDomain::query()->where('site_id', $site->getKey())->exists()) {
            SiteDomain::factory()->site($site)->language($language)->default()->create();
        }

        SeedBlogPublishingSurfaceAction::run();

        foreach (self::Articles as $row) {
            $article = Article::query()
                ->where('site_id', $site->getKey())
                ->where('name', $row['name'])
                ->first();
            $publishedAt = CarbonImmutable::parse($row['published'], 'UTC')->startOfDay();

            if (! $article instanceof Article) {
                $article = Article::factory()
                    ->site($site)
                    ->withTranslations(data: ['title' => $row['name']])
                    ->withExampleImages()
                    ->create(['name' => $row['name']]);
            }

            // Repair earlier fixture rows too: future dates and expired windows
            // otherwise leave a populated admin table with an empty public blog.
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
                && $basePath === $appPath,
            RuntimeException::class,
            'Blog screenshot fixtures require the explicit disposable local screenshot environment.',
        );
    }
}
