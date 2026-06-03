<?php

declare(strict_types=1);

use Capell\Blog\Actions\BuildArticleMetaDataAction;
use Capell\Blog\Models\Article;
use Capell\Core\Models\Site;
use Capell\Tags\Enums\TagTypeEnum;
use Capell\Tags\Models\Tag;
use Capell\Tests\Fixtures\Models\User;
use Illuminate\Support\Facades\Log;

it('omits tag links instead of failing when no tag page exists', function (): void {
    Log::spy();

    $site = Site::factory()->withTranslations()->create();
    $language = blogTestLanguage($site->language);
    $article = Article::factory()->site($site)->withTranslations()->create();
    $tag = Tag::factory()->site($site)->translate($language)->type(TagTypeEnum::Page)->create();
    $article->tags()->attach($tag);

    $data = BuildArticleMetaDataAction::run(
        page: $article,
        site: $site,
        language: $language,
    );

    expect($data->tags)->toHaveCount(1)
        ->and($data->tagLinks)->toBe([])
        ->and($data->tagPage)->toBeNull();

    Log::shouldHaveReceived('warning')->once();
});

it('exposes article reading and schema metadata', function (): void {
    $site = Site::factory()->withTranslations()->create();
    $language = blogTestLanguage($site->language);
    $author = User::factory()->create(['name' => 'Ada Editor']);
    $article = Article::factory()
        ->site($site)
        ->state(['created_by' => $author->getKey()])
        ->withTranslations()
        ->create([
            'visible_from' => now()->subDay(),
            'updated_at' => now(),
        ]);

    $content = '<p>' . implode(' ', array_fill(0, 401, 'word')) . '</p>';
    $article->translation()->update(['content' => $content]);
    $article->load(['creator', 'translation']);

    $data = BuildArticleMetaDataAction::run(
        page: $article,
        site: $site,
        language: $language,
        withAuthor: true,
    );

    expect($data->authorName)->toBe('Ada Editor')
        ->and($data->publishedAt?->toDateString())->toBe($article->visible_from?->toDateString())
        ->and($data->modifiedAt?->toDateString())->toBe($article->updated_at?->toDateString())
        ->and($data->readingTimeMinutes)->toBe(3);
});
