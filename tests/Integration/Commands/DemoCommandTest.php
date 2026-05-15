<?php

declare(strict_types=1);

use Capell\Blog\Enums\BlogPageTypeEnum;
use Capell\Blog\Models\Article;
use Capell\Core\Models\Language;
use Capell\Core\Models\Page;
use Capell\Core\Models\Site;
use Capell\LayoutBuilder\Models\Element;
use Capell\Tags\Enums\TagTypeEnum;
use Capell\Tags\Models\Tag;
use Illuminate\Console\Command;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\File;

use function Pest\Laravel\artisan;

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
        ->with(['tags', 'translations'])
        ->get();

    expect($articles)->toHaveCount(2);

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
        ->and(Element::query()->where('key', 'blog-hero')->exists())->toBeTrue()
        ->and(Element::query()->where('key', 'article-hero')->exists())->toBeTrue();

    $blogPage->translations->each(function ($translation): void {
        expect($translation->meta['hero'] ?? null)->toContain(__('capell-blog::generic.latest_articles'));
    });

    $articles->each(function (Article $article): void {
        $article->translations->each(function ($translation): void {
            expect($translation->meta['hero'] ?? null)->toContain($translation->title);
        });
    });
});
