<?php

declare(strict_types=1);

use Capell\Blog\Enums\BlogPageTypeEnum;
use Capell\Blog\Enums\LivewirePageComponentEnum;
use Capell\Blog\Models\Article;
use Capell\Core\Models\Blueprint;
use Capell\Core\Models\Language;
use Capell\Core\Models\Page;
use Capell\Core\Models\Site;
use Capell\LayoutBuilder\Models\Block;
use Capell\Tags\Enums\TagTypeEnum;
use Capell\Tags\Models\Tag;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\File;

use function Pest\Laravel\artisan;

it('registers blog model aliases before demo content is created', function (): void {
    $originalMorphMap = Relation::morphMap();
    $morphMapWithoutArticle = array_filter(
        $originalMorphMap,
        fn (string $modelClass): bool => $modelClass !== Article::class,
    );

    Relation::morphMap($morphMapWithoutArticle, merge: false);

    artisan('capell:blog-demo', [
        '--sites' => 'Missing Site',
    ])
        ->expectsOutput('Unable to find any sites for: Missing Site')
        ->assertExitCode(Command::FAILURE);

    expect(Relation::morphMap())->toHaveKey('article', Article::class);

    Relation::morphMap($originalMorphMap, merge: false);
});

it('runs demo command and creates articles and tags for the site', function (): void {
    $capellDirectory = storage_path('app/capell');
    $demoDirectory = $capellDirectory . '/demo';

    File::deleteDirectory($demoDirectory);

    $sourceDemoDirectory = realpath(__DIR__ . '/../../../../../packages/demo-kit/demo');

    throw_if($sourceDemoDirectory === false, RuntimeException::class, 'Demo fixtures directory not found.');

    $demoCopiedToStorage = File::copyDirectory($sourceDemoDirectory, $demoDirectory);

    expect($demoCopiedToStorage)->toBeTrue();

    /** @var Language $language */
    $language = Language::factory()->create([
        'code' => 'en',
    ]);

    /** @var Site $site */
    $site = Site::factory()->language($language)->withTranslations()->create();

    $site->refresh();
    $site->loadMissing('languages', 'language');

    artisan('capell:blog-demo', [
        '--sites' => $site->name,
        '--limit' => 2,
    ])
        ->expectsOutput('Setting up demo blog for site: ' . $site->name)
        ->expectsOutput('Blog demo setup completed for selected sites.')
        ->assertExitCode(Command::SUCCESS);

    /** @var class-string<Article> $articleModel */
    $articleModel = Article::class;

    /** @var Collection<int, Article> $articles */
    $articles = $articleModel::query()
        ->where('site_id', $site->id)
        ->whereRelation('type', 'key', BlogPageTypeEnum::Article->value)
        ->with(['pageUrl', 'tags', 'translations'])
        ->get();

    expect($articles)->toHaveCount(2);

    $articles->each(function (Article $article): void {
        expect($article->pageUrl)->not()->toBeNull()
            ->and($article->pageUrl?->url)->toStartWith('/blog/');
    });

    $archiveType = Blueprint::query()
        ->where('key', BlogPageTypeEnum::Archive->value)
        ->pageType()
        ->first();

    expect($archiveType?->component)->toBe(LivewirePageComponentEnum::ArchivePage->value)
        ->and($archiveType?->is_livewire)->toBeTrue();

    $articlesWithTagsCount = $articles
        ->filter(fn (Article $article): bool => $article->tags->isNotEmpty())
        ->count();

    expect($articlesWithTagsCount)->toBe(2);

    /** @var class-string<Tag> $tagModel */
    $tagModel = Tag::class;
    $pageTagsCount = $tagModel::query()
        ->where('type', TagTypeEnum::Page->value)
        ->count();

    expect($pageTagsCount)->toBeGreaterThanOrEqual(1);

    $articleLinkedToPageTag = $articles->first(fn (Article $article): bool => $article->tags->contains(fn (Tag $tag): bool => $tag->type === TagTypeEnum::Page->value));

    expect($articleLinkedToPageTag)->not()->toBeNull();

    $blogPage = Page::query()
        ->with(['layout', 'translations'])
        ->where('site_id', $site->id)
        ->whereRelation('type', 'key', 'blog')
        ->first();

    expect($blogPage)->not()->toBeNull()
        ->and($blogPage->layout?->containers)->toHaveKey('hero')
        ->and(Block::query()->where('key', 'blog-hero')->exists())->toBeTrue()
        ->and(Block::query()->where('key', 'article-hero')->exists())->toBeTrue();

    $blogPage->translations->each(function ($translation): void {
        expect($translation->meta['hero'] ?? null)->toContain(__('capell-blog::generic.latest_articles'));
    });

    $articles->each(function (Article $article): void {
        $article->translations->each(function ($translation): void {
            expect($translation->meta['hero'] ?? null)->toContain($translation->title);
        });
    });
});

it('does not rewrite existing non-demo articles when refreshing demo copy', function (): void {
    $capellDirectory = storage_path('app/capell');
    $demoDirectory = $capellDirectory . '/demo';

    File::deleteDirectory($demoDirectory);

    $sourceDemoDirectory = realpath(__DIR__ . '/../../../../../packages/demo-kit/demo');

    throw_if($sourceDemoDirectory === false, RuntimeException::class, 'Demo fixtures directory not found.');

    expect(File::copyDirectory($sourceDemoDirectory, $demoDirectory))->toBeTrue();

    /** @var Language $language */
    $language = Language::factory()->create([
        'code' => 'en',
    ]);

    /** @var Site $site */
    $site = Site::factory()->language($language)->withTranslations()->create();
    $site->refresh()->loadMissing('languages', 'language');

    /** @var Article $existingArticle */
    $existingArticle = Article::factory()
        ->site($site)
        ->withTranslations($language, [
            'title' => 'Editorial Strategy',
            'content' => '<p>Real editorial copy that must stay untouched.</p>',
            'meta' => [
                'slug' => 'editorial-strategy',
                'summary' => 'Original editorial summary.',
            ],
        ])
        ->create(['name' => 'Editorial Strategy']);

    artisan('capell:blog-demo', [
        '--sites' => $site->name,
        '--limit' => 1,
    ])->assertExitCode(Command::SUCCESS);

    $existingArticle->refresh()->load('translations');
    $translation = $existingArticle->translations->firstWhere('language_id', $language->id);

    expect($translation?->title)->toBe('Editorial Strategy')
        ->and($translation?->content)->toBe('<p>Real editorial copy that must stay untouched.</p>')
        ->and($translation?->meta['summary'] ?? null)->toBe('Original editorial summary.')
        ->and($translation?->meta['capell_blog_demo'] ?? false)->toBeFalse();
});
