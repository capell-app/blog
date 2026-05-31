<?php

declare(strict_types=1);

use Capell\Blog\Filament\Configurators\Blocks\RelatedBlockConfigurator;
use Capell\Blog\Filament\Resources\Articles\Tables\ArticlePagesTable;
use Capell\Blog\Models\Article;
use Capell\Core\Models\Blueprint;
use Capell\Core\Models\Language;
use Capell\Core\Models\PageUrl;
use Capell\Core\Models\Site;
use Capell\Tags\Models\Tag;
use Filament\Forms\Components\Select;
use Filament\Schemas\Components\Tabs;
use Filament\Schemas\Schema;
use Filament\Tables\Contracts\HasTable;
use Illuminate\Support\HtmlString;
use Mockery\MockInterface;

it('builds related article block schemas for option and edit workflows', function (): void {
    Blueprint::factory()->count(2)->page()->create(['status' => true]);

    $configurator = new RelatedBlockConfigurator;

    $optionComponents = $configurator->make(Schema::make()->operation('createOption'));
    $editComponents = $configurator->make(Schema::make()->operation('edit'));

    $optionNames = blogAdminComponentNames($optionComponents);
    $editNames = blogAdminComponentNames($editComponents);

    $excludeTypesSelect = collect(flattenBlogAdminComponents($editComponents))
        ->first(fn (mixed $component): bool => $component instanceof Select && $component->getName() === 'exclude_types');

    expect($optionNames)->toContain('key', 'translations')
        ->and($editNames)->toContain(
            'exclude_parent',
            'exclude_types',
            'limit',
            'pagination',
            'cache_frequency',
            'component',
        )
        ->and($excludeTypesSelect)->toBeInstanceOf(Select::class)
        ->and($excludeTypesSelect->getOptions())->toHaveCount(2)
        ->and(collect(flattenBlogAdminComponents($editComponents))->contains(
            fn (mixed $component): bool => $component instanceof Tabs,
        ))->toBeTrue();
});

it('applies article admin table filters search indicators and URL presentation from model state', function (): void {
    $language = Language::factory()->create([
        'name' => 'English',
        'code' => 'en',
    ]);
    $site = Site::factory()
        ->recycle($language)
        ->hasSiteDomains()
        ->withTranslations($language)
        ->create(['name' => 'Primary Site']);
    $canonical = Article::factory()
        ->site($site)
        ->withTranslations($language)
        ->create(['name' => 'Canonical article']);
    $article = Article::factory()
        ->site($site)
        ->withTranslations($language)
        ->create([
            'name' => 'Article search target',
            'meta' => ['canonical_page_id' => $canonical->getKey()],
        ]);
    $tag = Tag::factory()->create(['name' => ['en' => 'Launch']]);
    $article->tags()->sync([$tag->getKey()]);

    PageUrl::factory()
        ->page($article)
        ->site($site)
        ->language($language)
        ->create(['url' => '/article-search-target']);

    $article->load(['pageUrls.siteDomain', 'translations.language', 'site']);
    $loadedLivewire = blogAdminTableLivewire([
        'filter' => ['language_id' => $language->getKey()],
    ]);
    $unloadedLivewire = blogAdminTableLivewire([], loaded: false);

    $urlState = blogArticleTableInvoke('getUrlColumnState', $article, $loadedLivewire);
    $languageOptions = blogArticleTableInvoke('getLanguageOptions', $loadedLivewire);
    $languageSearchResults = blogArticleTableInvoke('getLanguageSearchResults', $loadedLivewire, 'Eng');
    $emptyLanguageOptions = blogArticleTableInvoke('getLanguageOptions', $unloadedLivewire);
    $filterIndicators = blogArticleTableInvoke('indicateFilter', [
        'language_id' => $language->getKey(),
        'canonical_page_id' => $canonical->getKey(),
    ]);
    $tagIndicators = blogArticleTableInvoke('indicateTagsFilter', ['value' => $tag->getKey()]);

    $languageFilteredQuery = Article::query();
    blogArticleTableInvoke('applyFilterQuery', $languageFilteredQuery, [
        'language_id' => $language->getKey(),
        'canonical_page_id' => $canonical->getKey(),
    ]);

    $tagFilteredQuery = Article::query();
    blogArticleTableInvoke('applyTagsFilterQuery', $tagFilteredQuery, ['value' => $tag->getKey()]);

    $emptyTagFilteredQuery = Article::query();
    blogArticleTableInvoke('applyTagsFilterQuery', $emptyTagFilteredQuery, ['value' => null]);

    $nameSearchQuery = Article::query();
    blogArticleTableInvoke('applyNameSearch', $nameSearchQuery, 'search target');

    expect(blogArticleTableInvoke('recordClasses', $article))->toBeNull()
        ->and($urlState)->toBeInstanceOf(HtmlString::class)
        ->and($urlState->toHtml())->toContain('/article-search-target')
        ->and($languageOptions)->toHaveKey($language->getKey())
        ->and($languageSearchResults)->toHaveKey($language->getKey())
        ->and($emptyLanguageOptions)->toBe([])
        ->and($filterIndicators)->toHaveKeys(['language_id', 'canonical_page_id'])
        ->and($tagIndicators)->toHaveKey('tags')
        ->and($languageFilteredQuery->pluck('id')->all())->toBe([$article->getKey()])
        ->and($tagFilteredQuery->pluck('id')->all())->toBe([$article->getKey()])
        ->and($emptyTagFilteredQuery->whereKey($article->getKey())->exists())->toBeTrue()
        ->and($nameSearchQuery->toSql())->toContain('pages');

    $article->delete();

    expect(blogArticleTableInvoke('recordClasses', $article))->toBe('table-row-warning');
});

/**
 * @param  array<int, mixed>  $components
 * @return array<int, mixed>
 */
function flattenBlogAdminComponents(array $components): array
{
    $flattenedComponents = [];

    foreach ($components as $component) {
        $flattenedComponents[] = $component;
        if (! is_object($component)) {
            continue;
        }

        if (! method_exists($component, 'getDefaultChildComponents')) {
            continue;
        }

        $childComponents = $component->getDefaultChildComponents();

        if (is_array($childComponents)) {
            array_push($flattenedComponents, ...flattenBlogAdminComponents($childComponents));
        }
    }

    return $flattenedComponents;
}

/**
 * @param  array<int, mixed>  $components
 * @return array<int, string>
 */
function blogAdminComponentNames(array $components): array
{
    return collect(flattenBlogAdminComponents($components))
        ->filter(fn (mixed $component): bool => is_object($component) && method_exists($component, 'getName'))
        ->map(fn (mixed $component): string => $component->getName())
        ->values()
        ->all();
}

function blogArticleTableInvoke(string $method, mixed ...$arguments): mixed
{
    $reflection = new ReflectionMethod(ArticlePagesTable::class, $method);

    return $reflection->invoke(null, ...$arguments);
}

/**
 * @param  array<string, mixed>  $filterState
 */
function blogAdminTableLivewire(array $filterState, bool $loaded = true): HasTable&MockInterface
{
    $livewire = Mockery::mock(HasTable::class);
    $livewire->shouldReceive('makeFilamentTranslatableContentDriver')->andReturn(null)->byDefault();
    $livewire->shouldReceive('getTableFilterState')
        ->withAnyArgs()
        ->andReturnUsing(fn (?string $name = null): mixed => $name === null ? $filterState : ($filterState[$name] ?? []));
    $livewire->shouldReceive('isTableLoaded')->andReturn($loaded);

    return $livewire;
}
