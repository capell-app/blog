<?php

declare(strict_types=1);

namespace Capell\Blog\Filament\Resources\Articles\Tables;

use Capell\Admin\Enums\FilamentColorEnum;
use Capell\Admin\Filament\Actions\Table\ReplicatePageAction;
use Capell\Admin\Filament\Components\Tables\Actions\EditAction;
use Capell\Admin\Filament\Components\Tables\Columns\BlueprintColumn;
use Capell\Admin\Filament\Components\Tables\Columns\DateColumn;
use Capell\Admin\Filament\Components\Tables\Columns\IdentifierColumn;
use Capell\Admin\Filament\Components\Tables\Columns\LanguagesColumn;
use Capell\Admin\Filament\Components\Tables\Columns\MediaLibraryImageColumn;
use Capell\Admin\Filament\Components\Tables\Columns\Page\PageNameColumn;
use Capell\Admin\Filament\Components\Tables\Columns\SiteColumn;
use Capell\Admin\Filament\Components\Tables\Filters\DateFilter;
use Capell\Admin\Filament\Contracts\HasPageResource;
use Capell\Admin\Filament\Contracts\TableConfigurator;
use Capell\Admin\Filament\Contracts\ValidatesDelete;
use Capell\Admin\Support\Loader\SiteLoader;
use Capell\Blog\Models\Article;
use Capell\Core\Actions\GetEditPageResourceUrlAction;
use Capell\Core\Actions\PageDeletedAction;
use Capell\Core\Contracts\Pageable;
use Capell\Core\Models\Language; // adjust if different namespace
use Capell\Tags\Models\Tag;
use Filament\Actions\ActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\ForceDeleteBulkAction;
use Filament\Actions\RestoreBulkAction;
use Filament\Forms\Components\Select;
use Filament\Resources\Pages\ListRecords;
use Filament\Resources\Pages\Page as ResourcePage;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TrashedFilter;
use Filament\Tables\Table;
use Illuminate\Contracts\Database\Eloquent\Builder as BuilderContract;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\HtmlString;
use Illuminate\Support\LazyCollection;

class ArticlePagesTable implements TableConfigurator
{
    public static function configure(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(self::getTableQuery(...))
            ->defaultSort('updated_at', 'desc')
            ->columns(static::getTableColumns())
            ->filters(static::getTableFilters())
            ->filtersFormWidth('4xl')
            ->filtersFormColumns([
                'sm' => 2,
                'lg' => 3,
            ])
            ->columnManagerColumns(3)
            ->recordClasses(self::recordClasses(...))
            ->recordActions([
                EditAction::make(),
                ActionGroup::make([
                    ReplicatePageAction::make(),
                    DeleteAction::make()
                        ->after(self::afterRecordDeleted(...)),
                ])
                    ->color('gray'),
            ])
            ->toolbarActions([
                DeleteBulkAction::make()
                    ->before(self::beforeBulkDelete(...))
                    ->after(self::afterBulkDelete(...)),
                RestoreBulkAction::make(),
                ForceDeleteBulkAction::make()
                    ->after(self::afterRecordDeleted(...)),
            ])
            ->recordUrl(self::getRecordUrl(...));
    }

    protected static function getTableQuery(Builder $query, HasTable $livewire): Builder
    {
        return $query
            ->whereHas('site', self::includeTrashedSite(...))
            ->whereHas('type')
            ->with([
                'blueprint',
                'canonicalPage',
                'creator',
                'editor',
                'image',
                'site' => self::includeTrashedSite(...),
                'site.siteDomains',
                'translation' => fn (BuilderContract $query): BuilderContract => $query->with('language')
                    ->select(['translatable_id', 'translatable_type', 'language_id', 'title'])
                    ->when($livewire->getTableFilterState('filter')['language_id'], self::applyTranslationLanguageFilter(...)),
                'translations.language',
                'type',
                'pageUrls' => self::includeOrderedPageUrls(...),
                'pageUrl.siteDomain',
            ]);
    }

    protected static function recordClasses(Pageable $record): ?string
    {
        return $record->deleted_at !== null ? 'table-row-warning' : null;
    }

    protected static function afterRecordDeleted(Pageable $record): void
    {
        PageDeletedAction::run($record);
    }

    protected static function beforeBulkDelete(HasTable&ValidatesDelete $livewire, DeleteBulkAction $action, EloquentCollection|Collection|LazyCollection $records): void
    {
        $records->each(function (Pageable $record) use ($livewire, $action): void {
            if (! $livewire->validateDelete($record)) {
                $action->cancel();
            }
        });
    }

    protected static function afterBulkDelete(DeleteBulkAction $action, Collection $records): void
    {
        $records->each(self::afterRecordDeleted(...));
    }

    protected static function getRecordUrl(Pageable $record): ?string
    {
        return GetEditPageResourceUrlAction::run($record);
    }

    protected static function includeTrashedSite(BuilderContract $query): BuilderContract
    {
        return $query->withTrashed();
    }

    protected static function applyTranslationLanguageFilter(BuilderContract $query, int $languageId): BuilderContract
    {
        return $query->where('language_id', $languageId);
    }

    protected static function includeOrderedPageUrls(BuilderContract $query): BuilderContract
    {
        return $query->with('siteDomain')->ordered();
    }

    protected static function getTableColumns(): array
    {
        return [
            IdentifierColumn::make('id'),
            PageNameColumn::make('name')
                ->wrap()
                ->sortable()
                ->children(false)
                ->ancestors(false)
                ->searchable(query: self::applyNameSearch(...))
                ->toggleable(),
            TextColumn::make('translation.title')
                ->label(__('capell-admin::table.title'))
                ->html()
                ->toggleable(isToggledHiddenByDefault: true),
            SiteColumn::make('site.name')
                ->color(FilamentColorEnum::LightGray->value)
                ->hidden(self::shouldHideSiteColumn(...)),
            TextColumn::make('url')
                ->label(__('capell-admin::table.url'))
                ->color('primary')
                ->disabledClick()
                ->html()
                ->searchable(query: self::applyUrlSearch(...))
                ->getStateUsing(self::getUrlColumnState(...))
                ->toggleable(isToggledHiddenByDefault: true),
            MediaLibraryImageColumn::make('image')
                ->collection('image')
                ->toggleable()
                ->alignCenter()
                ->width(0),
            LanguagesColumn::make('translations.language'),
            TextColumn::make('layout.name')
                ->label(__('capell-admin::table.layout'))
                ->sortable()
                ->limit(30)
                ->size('sm')
                ->color(FilamentColorEnum::LightGray->value)
                ->toggleable()
                ->width(0),
            BlueprintColumn::make('type.name')
                ->toggleable(isToggledHiddenByDefault: true),
            TextColumn::make('creator.name')
                ->label(__('capell-admin::table.created_by'))
                ->toggleable(isToggledHiddenByDefault: true),
            DateColumn::make('created_at'),
            DateColumn::make('updated_at'),
            DateColumn::make('deleted_at'),
        ];
    }

    protected static function shouldHideSiteColumn(HasTable $livewire): bool
    {
        return (($livewire instanceof ListRecords && $livewire->activeTab !== null)
                && ! in_array($livewire->getTableFilterState('site_id'), [null, []], true))
            || SiteLoader::getTotalSites() <= 1;
    }

    protected static function getUrlColumnState(Pageable $record, HasTable $livewire): ?HtmlString
    {
        $pageUrl = null;
        $languageId = $livewire->getTableFilterState('filter')['language_id'] ?? null;
        if ($languageId !== null && $languageId !== '') {
            $pageUrl = $record->pageUrls->firstWhere('language_id', $languageId);
        }

        if ($pageUrl === null) {
            $pageUrl = $record->pageUrls->first();
        }

        if ($pageUrl === null) {
            return null;
        }

        $shortUrl = str($pageUrl->url)->limit(40);

        return new HtmlString("<a href='" . $pageUrl->full_url . "' target='_blank'>" . $shortUrl . '</a>');
    }

    protected static function applyNameSearch(Builder $query, string $search): Builder
    {
        return $query->where('name', 'like', sprintf('%%%s%%', $search))
            ->orWhereHas(
                'translations',
                fn (BuilderContract $query): BuilderContract => $query->where('title', 'like', sprintf('%%%s%%', $search)),
            )
            ->orderByRaw("CAST(IFNULL(NULLIF(POSITION(? IN pages.name), 0), 'void') AS UNSIGNED)", [$search]);
    }

    protected static function applyUrlSearch(Builder $query, string $search): Builder
    {
        return $query->whereHas(
            'pageUrl',
            fn (BuilderContract $query): BuilderContract => $query->where('url', 'like', sprintf('%%%s%%', $search))
                ->orWhereHas(
                    'site',
                    fn (BuilderContract $query): BuilderContract => $query->whereHas(
                        'siteDomain',
                        fn (BuilderContract $query): BuilderContract => self::applyFullUrlSearch($query, $search),
                    ),
                )
                ->orWhereHas(
                    'pageable',
                    fn (BuilderContract $query): BuilderContract => $query->where('name', 'like', sprintf('%%%s%%', $search)),
                ),
        );
    }

    protected static function applyFullUrlSearch(BuilderContract $query, string $search): BuilderContract
    {
        $query->whereColumn('site_domains.language_id', 'page_urls.language_id');

        if (DB::getDriverName() === 'sqlite') {
            return $query->whereRaw(
                "site_domains.scheme || '://' || site_domains.domain || site_domains.path || page_urls.url like ?",
                [sprintf('%%%s%%', $search)],
            );
        }

        return $query->whereRaw(
            "CONCAT(site_domains.scheme, '://', site_domains.domain, COALESCE(site_domains.path, ''), page_urls.url) like ?",
            [sprintf('%%%s%%', $search)],
        );
    }

    protected static function getTableFilters(): array
    {
        return [
            SelectFilter::make('site_id')
                ->label(__('capell-admin::form.site'))
                ->searchable()
                ->preload()
                ->relationship(
                    name: 'site',
                    titleAttribute: 'name',
                    modifyQueryUsing: self::applyOrderedQuery(...),
                ),

            SelectFilter::make('layout_id')
                ->label(__('capell-admin::form.layout'))
                ->searchable()
                ->preload()
                ->relationship(
                    name: 'layout',
                    titleAttribute: 'name',
                    modifyQueryUsing: self::applyEnabledOrderedQuery(...),
                ),

            SelectFilter::make('blueprint_id')
                ->label(__('capell-admin::form.page_type'))
                ->searchable()
                ->preload()
                ->relationship(
                    name: 'type',
                    titleAttribute: 'name',
                    modifyQueryUsing: self::applyBlueprintFilterQuery(...),
                ),

            self::getTagsFilter(),

            Filter::make('filter')
                ->columnSpan(['default' => 1, 'md' => 3])
                ->columns(['default' => 1, 'md' => 3])
                ->schema([
                    Select::make('language_id')
                        ->label(__('capell-admin::table.language'))
                        ->searchable()
                        ->preload()
                        ->options(self::getLanguageOptions(...))
                        ->getSearchResultsUsing(self::getLanguageSearchResults(...)),
                ])
                ->query(self::applyFilterQuery(...))
                ->indicateUsing(self::indicateFilter(...)),

            DateFilter::make('visible_from')
                ->label(__('capell-admin::form.publish_date')),

            TrashedFilter::make()
                ->native(false),
        ];
    }

    protected static function applyOrderedQuery(Builder $query): Builder
    {
        return $query->ordered();
    }

    protected static function applyEnabledOrderedQuery(Builder $query): Builder
    {
        return $query->enabled()->ordered();
    }

    protected static function applyBlueprintFilterQuery(Builder $query, ResourcePage|HasPageResource $livewire): Builder
    {
        return $query->enabled()
            ->pageType()
            ->adminResource($livewire::getResource()::getResourceName());
    }

    protected static function getLanguageOptions(HasTable $livewire): array
    {
        if (! $livewire->isTableLoaded()) {
            return [];
        }

        return self::getLanguageSearchResults($livewire);
    }

    protected static function applyFilterQuery(Builder $query, array $data): void
    {
        $languageId = $data['language_id'] ?? null;
        if ($languageId !== null && $languageId !== '') {
            $query->whereHas(
                'translations',
                fn (BuilderContract $query): BuilderContract => $query->where('language_id', (int) $languageId),
            );
        }

        $canonicalPageId = $data['canonical_page_id'] ?? null;
        if ($canonicalPageId !== null && $canonicalPageId !== '') {
            $query->where('meta->canonical_page_id', $canonicalPageId);
        }
    }

    protected static function indicateFilter(array $data): array
    {
        $indicators = [];

        if (isset($data['language_id']) && $data['language_id'] !== null && $data['language_id'] !== '') {
            /** @var class-string<Language> $model */
            $model = Language::class;

            $indicators['language_id'] = __(
                'capell-admin::filter.language',
                ['search' => $model::query()->find($data['language_id'], 'name')?->name],
            );
        }

        if (isset($data['canonical_page_id']) && $data['canonical_page_id'] !== null && $data['canonical_page_id'] !== '') {
            /** @var class-string<Article> $model */
            $model = Article::class;

            $indicators['canonical_page_id'] = __(
                'capell-admin::filter.canonical_page',
                ['search' => $model::query()->where('id', $data['canonical_page_id'])->value('name')],
            );
        }

        return $indicators;
    }

    protected static function getLanguageSearchResults(HasTable $livewire, ?string $search = null): array
    {
        /* @var class-string<Language> $model */
        $model = Language::class;

        $activeTabSiteId = $livewire instanceof ListRecords ? $livewire->activeTab : null;

        $query = $model::query();

        if ($activeTabSiteId !== null) {
            $query->whereHas(
                'sites',
                fn (BuilderContract $query): BuilderContract => $query->where('sites.id', $activeTabSiteId),
            );
        }

        if ($search !== null && $search !== '') {
            $query
                ->where('name', 'like', sprintf('%%%s%%', $search))
                ->orWhere('code', 'like', sprintf('%%%s%%', $search));
        }

        return $query
            ->ordered()
            ->get()
            ->pluck('name', 'id')
            ->all();
    }

    protected static function getTagsFilter(): SelectFilter
    {
        return SelectFilter::make('tags')
            ->label(__('capell-blog::form.tags'))
            ->searchable()
            ->preload()
            ->relationship(
                name: 'tags',
                titleAttribute: 'name',
                modifyQueryUsing: self::modifyTagsFilterQuery(...),
            )
            ->query(self::applyTagsFilterQuery(...))
            ->indicateUsing(self::indicateTagsFilter(...));
    }

    protected static function modifyTagsFilterQuery(Builder $query, HasTable $livewire): void
    {
        $siteId = $livewire instanceof ListRecords ? $livewire->activeTab : null;

        if (in_array($siteId, [null, '', '0'], true)) {
            $query->with('site')->orderBy('site_id');
        } else {
            $query->where(fn (Builder $builder): Builder => $builder->where('site_id', $siteId)->orWhereNull('site_id'));
            $query->whereHas('pages', fn (BuilderContract $builder): BuilderContract => $builder->where('site_id', $siteId));
        }

        $languageId = $livewire->getTableFilterState('filter')['language_id'] ?? null;
        if ($languageId === null) {
            return;
        }

        /** @var class-string<Language> $model */
        $model = Language::class;

        $code = $model::query()->find($languageId, 'code')?->code;
        if ($code !== null && $code !== '') {
            $query->whereRaw('JSON_EXTRACT(`tags`.`name`, ' . DB::getPdo()->quote('$.' . $code) . ') IS NOT NULL');
        }
    }

    protected static function applyTagsFilterQuery(Builder $query, array $data): Builder
    {
        $value = $data['value'] ?? null;

        if (! $value) {
            return $query;
        }

        return $query->whereHas(
            'tags',
            fn (BuilderContract $builder): BuilderContract => $builder->where('tags.id', (int) $value),
        );
    }

    protected static function indicateTagsFilter(array $state): array
    {
        $indicators = [];
        $value = $state['value'] ?? null;

        if ($value) {
            $indicators['tags'] = __(
                'capell-layout-builder::filter.tag',
                ['search' => Tag::query()->find($value)?->name],
            );
        }

        return $indicators;
    }
}
