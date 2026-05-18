<?php

declare(strict_types=1);

namespace Capell\Blog\Console\Commands;

use Capell\Blog\Actions\CreateBlogHeroDemoContentAction;
use Capell\Blog\Actions\CreateBlogPagesAction;
use Capell\Blog\Actions\EnsureArticlePublishingDefaultsAction;
use Capell\Blog\Enums\BlogLayoutEnum;
use Capell\Blog\Enums\BlogPageTypeEnum;
use Capell\Blog\Models\Article;
use Capell\Blog\Support\BlogModelRegistrar;
use Capell\Blog\Support\Creator\ArticleCreator;
use Capell\Core\Contracts\Pageable;
use Capell\Core\Enums\BlueprintSubjectEnum;
use Capell\Core\Models\Blueprint;
use Capell\Core\Models\Language;
use Capell\Core\Models\Layout;
use Capell\Core\Models\Page;
use Capell\Core\Models\Site;
use Capell\DemoKit\Actions\BuildDemoGenerationPlanAction;
use Capell\DemoKit\Console\Commands\Concerns\HasSitesOption;
use Capell\DemoKit\Data\DemoSiteGenerationPlanData;
use Capell\DemoKit\Support\Creator\DemoCreator;
use Capell\Tags\Enums\TagTypeEnum;
use Capell\Tags\Models\Tag;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Auth\User;
use Illuminate\Support\Str;
use Symfony\Component\Console\Helper\ProgressBar;

class DemoCommand extends Command
{
    use HasSitesOption;

    private const DemoArticleMetaKey = 'capell_blog_demo';

    protected $signature = 'capell:blog-demo {--sites=} {--user=} {--limit=}';

    protected $description = 'Setup demo blog pages, tags and sample articles for selected sites.';

    private DemoCreator $demoCreator;

    private ?ProgressBar $progress = null;

    public function handle(): int
    {
        BlogModelRegistrar::register();

        $siteNames = $this->parseSitesOption();

        if ($siteNames === []) {
            $this->error('No sites selected or provided.');

            return self::FAILURE;
        }

        $sites = $this->resolveSites($siteNames);

        if ($sites->isEmpty()) {
            $this->error('Unable to find any sites for: ' . implode(', ', $siteNames));

            return self::FAILURE;
        }

        $user = $this->resolveUser();
        $limit = $this->parseLimitOption();

        foreach ($sites as $index => $site) {
            if ($index > 0) {
                $this->newLine();
            }

            $this->runDemoForSite($site, $user, $limit);
        }

        $this->info('Blog demo setup completed for selected sites.');

        return self::SUCCESS;
    }

    /**
     * @return list<string>
     */
    private function parseSitesOption(): array
    {
        $sitesOption = $this->option('sites');

        if (is_string($sitesOption) && $sitesOption !== '') {
            return [trim($sitesOption)];
        }

        if (is_array($sitesOption)) {
            return array_values(array_filter(array_map(
                static fn (mixed $siteName): string => is_string($siteName) ? trim($siteName) : '',
                $sitesOption,
            ), static fn (string $siteName): bool => $siteName !== ''));
        }

        return $this->getDemoSites() ?? [];
    }

    /**
     * @param  list<string>  $siteNames
     * @return \Illuminate\Support\Collection<int, Site>
     */
    private function resolveSites(array $siteNames): \Illuminate\Support\Collection
    {
        /** @var class-string<Site> $siteModel */
        $siteModel = Site::class;

        return $siteModel::query()
            ->with(['languages'])
            ->whereIn('name', $siteNames)
            ->get();
    }

    private function resolveUser(): ?Model
    {
        $userOption = $this->option('user');

        if (! in_array($userOption, [null, false, ''], true)) {
            /** @var class-string<User> $userModel */
            $userModel = config('auth.providers.users.model');

            return $userModel::query()->find($userOption);
        }

        if (function_exists('auth') && auth()->check()) {
            $user = auth()->user();

            return $user instanceof Model ? $user : null;
        }

        return null;
    }

    private function parseLimitOption(): ?int
    {
        $limitOption = $this->option('limit');
        $limit = in_array($limitOption, [null, false, ''], true) ? null : (int) $limitOption;

        if ($limit !== null && $limit < 1) {
            $this->warn('The --limit option must be a positive integer. No demo pages will be created.');

            return null;
        }

        return $limit;
    }

    private function runDemoForSite(Site $site, ?Model $user, ?int $limit): void
    {
        $this->info('Setting up demo blog for site: ' . $site->name);
        $this->newLine();

        $this->demoCreator = resolve(DemoCreator::class, ['author' => $user]);

        $site->loadMissing('languages', 'language');

        $languages = $this->siteLanguages($site);
        $sitePlan = $this->buildDemoPlan($site, $languages, $limit);
        $pagesToCreate = $sitePlan->pageCount();
        $existingArticleCount = $this->countExistingArticles($site);
        $taggingSteps = min($existingArticleCount + $pagesToCreate, 50);

        $this->startProgress(1 + $pagesToCreate + $taggingSteps);

        $this->setProgressMessage('Ensuring required blog and ancillary pages exist');
        CreateBlogPagesAction::run($site);
        $this->advanceProgress();

        $this->setProgressMessage('Creating demo pages');
        $created = $this->createArticles($site, $user, $sitePlan, $languages, $limit);

        $this->setProgressMessage($created ? 'Demo pages created' : 'Demo pages not created');
        $this->setProgressMessage('Refreshing existing demo articles');
        $this->refreshExistingDemoArticles($site, $languages);
        $this->setProgressMessage('Existing demo articles refreshed');
        $this->setProgressMessage('Creating tags for site pages');
        $this->createArticleTags($site, $languages);
        $this->setProgressMessage('Tags created/updated');

        $this->setProgressMessage('Creating blog hero demo content');
        CreateBlogHeroDemoContentAction::run($site);
        $this->setProgressMessage('Blog hero demo content created');

        $this->finishProgress();
        $this->newLine();
    }

    /**
     * @param  Collection<int, Language>  $languages
     */
    private function createArticles(
        Site $site,
        ?Model $user,
        DemoSiteGenerationPlanData $sitePlan,
        Collection $languages,
        ?int $limit = null,
    ): bool {
        $createdCount = 0;

        EnsureArticlePublishingDefaultsAction::run();

        $type = Blueprint::query()
            ->where('key', BlogPageTypeEnum::Article->value)
            ->where('type', BlueprintSubjectEnum::Page->value)
            ->firstOrFail();

        $layout = Layout::query()
            ->where('key', BlogLayoutEnum::Article->value)
            ->firstOrFail();

        foreach ($sitePlan->pages as $pageData) {
            if ($limit !== null && $createdCount >= $limit) {
                break;
            }

            $createdCount += $this->createDemoArticleRecursive(
                $pageData->toContentTreeNode(),
                $site,
                $languages,
                '',
                $type,
                $layout,
                $user,
                $limit,
                $createdCount,
            );
        }

        return true;
    }

    /**
     * @param  Collection<int, Language>  $languages
     */
    private function refreshExistingDemoArticles(Site $site, Collection $languages): void
    {
        /** @var class-string<Article> $articleModel */
        $articleModel = Article::class;

        $articleModel::query()
            ->where('site_id', $site->id)
            ->whereRelation('type', 'key', BlogPageTypeEnum::Article->value)
            ->whereHas('translations', function ($query): void {
                $query->where('meta->' . self::DemoArticleMetaKey, true);
            })
            ->with(['translations'])
            ->get()
            ->each(function (Article $article) use ($languages): void {
                $data = [
                    'name' => $languages
                        ->mapWithKeys(fn (Language $language): array => [$language->code => $article->name])
                        ->all(),
                ];

                $this->refreshDemoArticleCopy($article, $languages, $data);
                $this->removeRandomDemoMedia($article);
            });
    }

    /**
     * @param  array<string, mixed>  $data
     * @param  Collection<int, Language>  $languages
     */
    private function createDemoArticleRecursive(
        array $data,
        Site $site,
        Collection $languages,
        string $parentName,
        Blueprint $type,
        Layout $layout,
        ?Model $author,
        ?int $limit,
        int $createdSoFar,
    ): int {
        if ($limit !== null && $createdSoFar >= $limit) {
            return 0;
        }

        $name = $this->translatedName($data);
        $fullName = $parentName === '' ? $name : sprintf('%s » %s', $parentName, $name);

        $this->setProgressMessage('Creating page: ' . $fullName);

        $articleCreator = resolve(ArticleCreator::class);

        $article = $this->demoCreator->createPage(
            $data,
            $site,
            $languages,
            type: $type,
            layout: $layout,
            createMedia: false,
            pageCreator: $articleCreator,
        );

        $this->refreshDemoArticleCopy($article, $languages, $data);
        $this->removeRandomDemoMedia($article);

        $this->advanceProgress();

        $created = 1;

        if (! isset($data['children']) || ($limit !== null && $createdSoFar + $created >= $limit)) {
            return $created;
        }

        foreach ($data['children'] as $child) {
            if ($limit !== null && $createdSoFar + $created >= $limit) {
                break;
            }

            $created += $this->createDemoArticleRecursive(
                $child,
                $site,
                $languages,
                $fullName,
                $type,
                $layout,
                $author,
                $limit,
                $createdSoFar + $created,
            );
        }

        return $created;
    }

    /**
     * @param  array<string, mixed>  $data
     * @param  Collection<int, Language>  $languages
     */
    private function refreshDemoArticleCopy(Pageable $article, Collection $languages, array $data): void
    {
        foreach ($languages as $language) {
            $languageCode = $language->getAttribute('code');
            if (! is_string($languageCode)) {
                continue;
            }

            if ($languageCode === '') {
                continue;
            }

            $title = Str::title((string) (is_array($data['name'] ?? null)
                ? ($data['name'][$languageCode] ?? $data['name']['en'] ?? $article->name)
                : $article->name));

            $content = $this->articleContent($title);
            $summary = $this->articleSummary($title);
            $slug = Str::slug($title);

            $translation = $article->translations()->firstOrNew(['language_id' => $language->id]);
            $meta = is_array($translation->meta) ? $translation->meta : [];
            $plainContent = Str::of($content)->stripTags()->squish()->toString();

            $translation->fill([
                'title' => $title,
                'content' => $content,
                'meta' => [
                    ...$meta,
                    'description' => Str::limit($plainContent, 160),
                    'label' => $title,
                    'link_text' => 'Read article',
                    'slug' => $slug,
                    'summary' => $summary,
                    self::DemoArticleMetaKey => true,
                ],
            ]);
            $translation->save();
        }
    }

    private function removeRandomDemoMedia(Pageable $article): void
    {
        if (! method_exists($article, 'clearMediaCollection')) {
            return;
        }

        $article->clearMediaCollection('image');
    }

    private function articleSummary(string $title): string
    {
        return match ($title) {
            'Customer Stories' => 'How teams use Capell to launch governed content workflows without losing frontend craft.',
            'Case Studies' => 'Delivery notes from real Capell builds, covering scope, structure, launch, and measurable outcomes.',
            'Support' => 'A practical support model for keeping Capell sites healthy after launch.',
            'News' => 'Product and platform updates for teams running Capell in production.',
            'Quality' => 'The checks that keep public output consistent across content, design, and deployment.',
            default => sprintf('%s notes for teams building structured, maintainable Capell websites.', $title),
        };
    }

    private function articleContent(string $title): string
    {
        $content = match ($title) {
            'Customer Stories' => [
                'Customer stories in Capell should show the operating model behind the outcome, not just a polished launch screen.',
                'Use article content to capture the brief, editorial constraints, reusable layout decisions, and the governance work that made the site maintainable after handover.',
            ],
            'Case Studies' => [
                'Case studies can explain the project shape without turning every customer win into a custom Blade template.',
                'The article model stores the story, taxonomy, publish date, and route. The layout decides how that proof appears beside the rest of the site.',
            ],
            'Support' => [
                'Support content should make ownership clear for editors, developers, and operators.',
                'A Capell support article can document release cadence, package upgrades, content QA, static generation, and incident response in one governed publishing flow.',
            ],
            'News' => [
                'News articles give product teams a reliable place to publish changes without creating new route code for each announcement.',
                'Keep the post focused on what changed, who it affects, and what editors or developers should do next.',
            ],
            'Quality' => [
                'Quality articles make the invisible checks visible: layout consistency, public-output safety, responsive behaviour, content freshness, and cache correctness.',
                'The same article shell can support release notes, QA findings, and implementation guidance while staying aligned with the wider Capell site design.',
            ],
            default => [
                sprintf('%s content should feel like part of the Capell product site, with practical detail and no placeholder filler.', $title),
                'The demo keeps article copy structured and portable so the public template owns design decisions while editors own the message.',
            ],
        };

        return collect($content)
            ->map(fn (string $paragraph): string => sprintf('<p>%s</p>', e($paragraph)))
            ->implode("\n");
    }

    /**
     * @return Collection<int, Language>
     */
    private function siteLanguages(Site $site): Collection
    {
        $languages = $site->languages;

        if ($languages->isNotEmpty()) {
            return $languages;
        }

        $site->loadMissing('language');

        return $site->language instanceof Language
            ? new Collection([$site->language])
            : new Collection;
    }

    /**
     * @param  Collection<int, Language>  $languages
     */
    private function buildDemoPlan(Site $site, Collection $languages, ?int $limit): DemoSiteGenerationPlanData
    {
        $languageCodes = $languages
            ->map(fn (Language $language): string => $language->code)
            ->filter(fn (string $languageCode): bool => $languageCode !== '')
            ->values()
            ->all();

        $plan = BuildDemoGenerationPlanAction::run([
            'sites' => [$site->name],
            'pages' => $limit,
            'languages' => $languageCodes,
        ]);

        return $plan->sites[0];
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function translatedName(array $data): string
    {
        $names = $data['name'] ?? null;

        if (! is_array($names)) {
            return Str::title((string) $names);
        }

        $name = $names['en'] ?? reset($names);

        return Str::title(is_scalar($name) ? (string) $name : '');
    }

    /**
     * @param  Collection<int, Language>  $languages
     */
    private function createArticleTags(Site $site, Collection $languages): void
    {
        /** @var class-string<Page> $pageModel */
        $pageModel = Page::class;

        /** @var class-string<Article> $articleModel */
        $articleModel = Article::class;

        $articles = $articleModel::query()
            ->where('site_id', $site->id)
            ->whereRelation('type', 'key', BlogPageTypeEnum::Article->value)
            ->with(['translations'])
            ->limit(50)
            ->get();

        $articles->each(function (Article $article) use ($languages, $pageModel): void {
            $page = $pageModel::query()->firstWhere('name', $article->name);

            $page ??= $article;

            if ($page instanceof Article || $page->parent_id === null) {
                $tag = $this->createPageTag($page, $languages);
            } else {
                $tag = $this->getPageTag($page, $languages->first());

                if (! $tag instanceof Tag) {
                    $tag = $this->createPageTag($page, $languages);
                }
            }

            $article->tags()->syncWithoutDetaching($tag);
            $this->advanceProgress();
        });
    }

    /**
     * @param  Collection<int, Language>  $languages
     */
    private function createPageTag(Pageable $page, Collection $languages): Tag
    {
        /** @var class-string<Tag> $tagModel */
        $tagModel = Tag::class;

        $tagNames = [];
        $tagSlugs = [];
        $tag = null;

        $languages->each(function (Language $language) use (&$tagNames, &$tagSlugs, $page, $tagModel, &$tag): void {
            $translation = $page->translations->firstWhere('language_id', $language->id);

            if ($translation === null) {
                return;
            }

            $tagNames[$language->code] = Str::title($translation->label);
            $tagSlugs[$language->code] = Str::slug($translation->label);

            if ($tag === null) {
                $tag = $tagModel::findFromString($translation->label, 'page', $language->code);
            }
        });

        if ($tag instanceof Tag) {
            $tag->update([
                'name' => $tagNames,
                'slug' => $tagSlugs,
            ]);

            return $tag;
        }

        return $tagModel::query()->create([
            'type' => TagTypeEnum::Page,
            'name' => $tagNames,
            'slug' => $tagSlugs,
        ]);
    }

    private function getPageTag(Pageable $page, Language $language): ?Tag
    {
        $root = method_exists($page, 'ancestors') ? $page->ancestors->first() : $page->parent;

        if ($root === null) {
            $root = $page;
        }

        /** @var class-string<Tag> $tagModel */
        $tagModel = Tag::class;

        $translation = $root->translations->firstWhere('language_id', $language->id);

        if ($translation === null) {
            return null;
        }

        return $tagModel::findFromString($translation->label, 'page', $language->code);
    }

    private function startProgress(int $max): void
    {
        $this->progress = $this->output->createProgressBar($max);
        $this->progress->setFormat(' [%bar%] %percent:3s%% | %message%');
        $this->progress->setMessage('');
    }

    private function setProgressMessage(string $message): void
    {
        if ($this->progress instanceof ProgressBar) {
            $this->progress->setMessage($message);
        }
    }

    private function advanceProgress(int $step = 1): void
    {
        if ($this->progress instanceof ProgressBar) {
            $this->progress->advance($step);
        }
    }

    private function finishProgress(): void
    {
        if ($this->progress instanceof ProgressBar) {
            $this->progress->finish();
            $this->newLine();
        }

        $this->progress = null;
    }

    private function countExistingArticles(Site $site): int
    {
        /** @var class-string<Article> $articleModel */
        $articleModel = Article::class;

        return min(
            $articleModel::query()
                ->where('site_id', $site->id)
                ->whereRelation('type', 'key', BlogPageTypeEnum::Article->value)
                ->count(),
            50,
        );
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function countContentNodes(array $data): int
    {
        $count = 1;

        if (isset($data['children']) && is_array($data['children'])) {
            foreach ($data['children'] as $child) {
                $count += $this->countContentNodes($child);
            }
        }

        return $count;
    }
}
