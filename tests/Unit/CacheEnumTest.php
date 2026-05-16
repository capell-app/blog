<?php

declare(strict_types=1);

use Capell\Blog\Actions\ClearBlogContentCacheAction;
use Capell\Blog\Enums\CacheEnum;
use Capell\Blog\Models\Article;
use Capell\Blog\Support\Loader\TagLoader;
use Capell\Core\Facades\CapellCore;
use Capell\Core\Models\Site;
use Capell\Frontend\Contracts\RenderedModelTracker;
use Capell\Tags\Enums\TagTypeEnum;
use Capell\Tags\Models\Tag;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

it('keeps paginated tag cache keys isolated by paginator name', function (): void {
    expect(CacheEnum::siteTags(1, 2, true, 10, 1, 'tags-main'))
        ->not->toBe(CacheEnum::siteTags(1, 2, true, 10, 1, 'tags-sidebar'));
});

it('keeps site tag cache keys isolated by invalidation version', function (): void {
    expect(CacheEnum::siteTags(1, 2, true, 50, null, 'tags-main', 1))
        ->not->toBe(CacheEnum::siteTags(1, 2, true, 50, null, 'tags-main', 2));
});

it('increments the site tag cache version when blog content changes', function (): void {
    $site = Site::factory()->withTranslations()->create();
    $language = $site->language;
    $versionKey = CacheEnum::siteTagsVersion((int) $site->getKey(), (int) $language->getKey());

    Cache::store()->forget($versionKey);

    $article = Article::withoutEvents(
        fn (): Article => Article::factory()->site($site)->create(),
    );

    ClearBlogContentCacheAction::run($article);

    expect(Cache::store()->get($versionKey))->toBe(1);
});

it('increments the site tag cache version and clears tag slug cache when tags change', function (): void {
    $site = Site::factory()->withTranslations()->create();
    $language = $site->language;
    $versionKey = CacheEnum::siteTagsVersion((int) $site->getKey(), (int) $language->getKey());
    $oldTagPageKey = CacheEnum::tagPage((int) $site->getKey(), (int) $language->getKey(), 'old-tag');
    $newTagPageKey = CacheEnum::tagPage((int) $site->getKey(), (int) $language->getKey(), 'new-tag');
    $tag = Tag::factory()->site($site)->type(TagTypeEnum::Page)->create([
        'name' => [$language->code => 'Old tag'],
        'slug' => [$language->code => 'old-tag'],
    ]);

    Cache::store()->forget($versionKey);
    CapellCore::rememberCache($oldTagPageKey, fn (): string => 'old cached tag', 0);
    CapellCore::rememberCache($newTagPageKey, fn (): string => 'new cached tag', 0);

    $tag->forceFill([
        'name' => [$language->code => 'New tag'],
        'slug' => [$language->code => 'new-tag'],
    ])->save();

    expect(Cache::store()->get($versionKey))->toBe(1)
        ->and(CapellCore::getFromCache($oldTagPageKey))->toBeNull()
        ->and(CapellCore::getFromCache($newTagPageKey))->toBeNull();
});

it('uses a new tag listing cache key after the first invalidation', function (): void {
    $site = Site::factory()->withTranslations()->create();
    $language = $site->language;
    $article = Article::factory()->site($site)->withTranslations()->create();

    $article->syncTagsWithType(['First tag'], TagTypeEnum::Page->value);

    $cachedTags = TagLoader::getTags($site, $language, 10, true, null, false, 'tags-main');
    expect($cachedTags)->toHaveCount(1);

    $article->syncTagsWithType(['First tag', 'Second tag'], TagTypeEnum::Page->value);

    expect(TagLoader::getTags($site, $language, 10, true, null, false, 'tags-main'))->toHaveCount(2);
});

it('tracks cached paginated tag listings for html cache invalidation', function (): void {
    $tracker = new class implements RenderedModelTracker
    {
        /** @var array<int, int> */
        public array $tracked = [];

        public function track(Model $model): void
        {
            $this->tracked[] = (int) $model->getKey();
        }

        public function trackByClass(Model $model, string $modelClass): void
        {
            $this->track($model);
        }

        public function tracked(string $modelType): int
        {
            return count($this->tracked);
        }
    };

    app()->instance(RenderedModelTracker::class, $tracker);

    $site = Site::factory()->withTranslations()->create();
    $language = $site->language;
    $article = Article::factory()->site($site)->withTranslations()->create();
    $tag = Tag::factory()->translate($language)->type(TagTypeEnum::Page)->create();
    $article->tags()->attach($tag);

    TagLoader::getTags($site, $language, 10, true, 1, true, 'tags-main');
    $tracker->tracked = [];

    TagLoader::getTags($site, $language, 10, true, 1, true, 'tags-main');

    expect($tracker->tracked)->toBe([(int) $tag->getKey()]);
});

it('uses the requested page when loading paginated tags', function (): void {
    $site = Site::factory()->withTranslations()->create();
    $language = $site->language;
    $article = Article::factory()->site($site)->withTranslations()->create();
    $firstTag = Tag::factory()->site($site)->type(TagTypeEnum::Page)->create([
        'name' => [$language->code => 'Alpha'],
        'slug' => [$language->code => 'alpha'],
        'order_column' => 1,
    ]);
    $secondTag = Tag::factory()->site($site)->type(TagTypeEnum::Page)->create([
        'name' => [$language->code => 'Beta'],
        'slug' => [$language->code => 'beta'],
        'order_column' => 2,
    ]);

    $article->tags()->attach([$firstTag->getKey(), $secondTag->getKey()]);

    $firstPage = TagLoader::getTags($site, $language, 1, true, 1, true, 'tags-main');
    $secondPage = TagLoader::getTags($site, $language, 1, true, 2, true, 'tags-main');

    expect($firstPage->first()?->getKey())->toBe($firstTag->getKey())
        ->and($secondPage->first()?->getKey())->toBe($secondTag->getKey());
});

it('resets cached paginated tag listing paths to the current request url', function (): void {
    $site = Site::factory()->withTranslations()->create();
    $language = $site->language;
    $article = Article::factory()->site($site)->withTranslations()->create();
    $tag = Tag::factory()->site($site)->type(TagTypeEnum::Page)->create([
        'name' => [$language->code => 'Alpha'],
        'slug' => [$language->code => 'alpha'],
    ]);

    $article->tags()->attach($tag);

    app()->instance('request', Request::create('https://example.test/first-tags-page'));
    $firstPage = TagLoader::getTags($site, $language, 1, true, 1, true, 'tags-main');
    $firstPageUrl = $firstPage->url(2);

    app()->instance('request', Request::create('https://example.test/second-tags-page'));
    $cachedPage = TagLoader::getTags($site, $language, 1, true, 1, true, 'tags-main');

    expect($firstPageUrl)->toContain('/first-tags-page?tags-main=2')
        ->and($cachedPage->url(2))->toContain('/second-tags-page?tags-main=2');
});
