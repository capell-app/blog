<?php

declare(strict_types=1);

use Capell\Blog\Filament\Configurators\Widgets\RelatedWidgetConfigurator;
use Capell\Blog\Filament\Resources\Articles\Tables\ArticlePagesTable;
use Capell\Blog\Models\Article;
use Capell\Core\Models\Blueprint;
use Capell\Core\Models\Language;
use Capell\Core\Models\Layout;
use Capell\Core\Models\PageUrl;
use Capell\Core\Models\Site;
use Capell\Tags\Models\Tag;
use Filament\Forms\Components\Select;
use Filament\Schemas\Components\Tabs;
use Filament\Schemas\Contracts\HasSchemas;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Support\HtmlString;
use Livewire\Component;
use Mockery\MockInterface;

it('builds related article widget schemas for option and edit workflows', function (): void {
    Blueprint::factory()->count(2)->page()->create(['status' => true]);

    $configurator = new RelatedWidgetConfigurator;

    $optionComponents = $configurator->make(Schema::make()->operation('createOption'));
    $editComponents = $configurator->make(Schema::make()->operation('edit'));

    $optionNames = blogAdminComponentNames($optionComponents);
    $editNames = blogAdminComponentNames($editComponents);

    $excludeTypesSelect = collect(flattenBlogAdminComponents($editComponents))
        ->first(fn (mixed $component): bool => $component instanceof Select && $component->getName() === 'exclude_types');

    throw_unless($excludeTypesSelect instanceof Select, RuntimeException::class, 'Expected an excluded article type selector.');

    expect($optionNames)->toContain('key', 'translations')
        ->and($editNames)->toContain(
            'exclude_parent',
            'exclude_types',
            'limit',
            'pagination',
            'cache_frequency',
            'component',
        )
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
    $type = Blueprint::factory()->page()->create(['key' => 'article']);
    $layout = Layout::factory()->create();
    $canonical = Article::factory()
        ->site($site)
        ->type($type)
        ->layout($layout)
        ->withTranslations($language)
        ->create(['name' => 'Canonical article']);
    $article = Article::factory()
        ->site($site)
        ->type($type)
        ->layout($layout)
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
    $filterState = [
        'filter' => ['language_id' => $language->getKey(), 'canonical_page_id' => $canonical->getKey()],
        'tags' => ['value' => $tag->getKey()],
    ];
    $loadedLivewire = blogAdminTableLivewire($filterState);
    $table = ArticlePagesTable::configure(Table::make($loadedLivewire));
    $urlColumn = $table->getColumn('url');
    throw_unless($urlColumn instanceof TextColumn, RuntimeException::class);
    $urlState = $urlColumn->record($article)->getState();
    throw_unless($urlState instanceof HtmlString, RuntimeException::class, 'Expected rendered article links.');
    $filter = $table->getFilter('filter');
    $tagsFilter = $table->getFilter('tags');
    throw_unless($filter instanceof Filter && $tagsFilter instanceof SelectFilter, RuntimeException::class);
    $languageSelect = $filter->getSchemaComponents()[0];
    throw_unless($languageSelect instanceof Select, RuntimeException::class);
    Schema::make($loadedLivewire)->components([$languageSelect])->getComponents();

    $languageFilteredQuery = $filter->apply(Article::query(), $filterState['filter']);
    $tagFilteredQuery = $tagsFilter->apply(Article::query(), $filterState['tags']);
    $emptyTagFilteredQuery = $tagsFilter->apply(Article::query(), ['value' => null]);
    $nameSearchQuery = Article::query();
    $nameColumn = $table->getColumn('name');
    throw_unless($nameColumn instanceof TextColumn, RuntimeException::class);
    $isFirst = true;
    $nameColumn->applySearchConstraint($nameSearchQuery, 'search target', $isFirst);

    expect($table->getRecordClasses($article))->toBe([])
        ->and($urlState->toHtml())->toContain('/article-search-target')
        ->and($languageSelect->getOptions())->toHaveKey($language->getKey())
        ->and($languageSelect->getSearchResults('Eng'))->toHaveKey($language->getKey())
        ->and($filter->getIndicators())->toHaveKeys(['language_id', 'canonical_page_id'])
        ->and($tagsFilter->getIndicators())->toHaveKey('tags')
        ->and($languageFilteredQuery->pluck('id')->all())->toBe([$article->getKey()])
        ->and($tagFilteredQuery->pluck('id')->all())->toBe([$article->getKey()])
        ->and($emptyTagFilteredQuery->whereKey($article->getKey())->exists())->toBeTrue()
        ->and($nameSearchQuery->pluck('id')->all())->toBe([$article->getKey()]);

    $unloadedLivewire = blogAdminTableLivewire([], loaded: false);
    Schema::make($unloadedLivewire)->components([$languageSelect])->getComponents();
    expect($languageSelect->getOptions())->toBe([]);

    $article->delete();

    expect($table->getRecordClasses($article))->toBe(['table-row-warning']);
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
            array_push($flattenedComponents, ...flattenBlogAdminComponents(array_values($childComponents)));
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

/**
 * @param  array<string, mixed>  $filterState
 */
function blogAdminTableLivewire(array $filterState, bool $loaded = true): Component&HasSchemas&HasTable&MockInterface
{
    $livewire = Mockery::mock(Component::class, HasSchemas::class, HasTable::class);
    $livewire->shouldIgnoreMissing();
    $livewire->shouldReceive('makeFilamentTranslatableContentDriver')->andReturn(null)->byDefault();
    $livewire->shouldReceive('getTableFilterState')
        ->withAnyArgs()
        ->andReturnUsing(fn (?string $name = null): mixed => $name === null ? $filterState : ($filterState[$name] ?? []));
    $livewire->shouldReceive('isTableLoaded')->andReturn($loaded);

    return $livewire;
}
