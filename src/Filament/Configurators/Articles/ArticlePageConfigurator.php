<?php

declare(strict_types=1);

namespace Capell\Blog\Filament\Configurators\Articles;

use Capell\Admin\Filament\Components\Forms\FixedWidthSidebar;
use Capell\Admin\Filament\Components\Forms\MediaLibraryFileUpload;
use Capell\Admin\Filament\Components\Forms\Page\LayoutSelect;
use Capell\Admin\Filament\Components\Forms\Page\SettingsSchema;
use Capell\Admin\Filament\Components\Forms\Page\SiteSelect;
use Capell\Admin\Filament\Components\Forms\PublishDatesGrid;
use Capell\Admin\Filament\Components\Forms\PublishSchema;
use Capell\Admin\Filament\Configurators\Pages\DefaultPageConfigurator;
use Capell\Admin\Filament\Livewire\PublishStatusPanel;
use Capell\Admin\Filament\Resources\Pages\RelationManagers\UrlsRelationManager;
use Capell\Blog\Filament\Components\Forms\Article\Tab\SettingsTab;
use Capell\Blog\Filament\Components\Forms\Article\TagsInput;
use Capell\Blog\Filament\Resources\Articles\ArticleResource;
use Capell\Blog\Models\Article;
use Capell\Blog\Support\Loader\BlogLoader;
use Capell\Core\Models\Page;
use Capell\Core\Models\Site;
use Closure;
use Filament\Schemas\Components\Livewire;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Tabs;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Illuminate\Contracts\Support\Arrayable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Override;

class ArticlePageConfigurator extends DefaultPageConfigurator
{
    protected bool $hasCreatePageSchema = false;

    #[Override]
    public static function relationManagers(Model $record): array
    {
        return [
            UrlsRelationManager::class,
        ];
    }

    protected static function modifyParentQueryUsing(Schema $configurator): Closure
    {
        return function (Builder $query) use ($configurator) {
            /** @var class-string<Site> $model */
            $model = Site::class;
            $rawState = $configurator->getRawState();
            $state = $rawState instanceof Arrayable ? $rawState->toArray() : $rawState;
            $siteId = is_array($state) && is_scalar($state['site_id'] ?? null)
                ? (int) $state['site_id']
                : null;

            $site = $siteId !== null ? $model::query()->find($siteId) : null;

            $blogPage = $site !== null ? BlogLoader::getBlogPage($site) : null;

            $query = $query->adminResource(
                ArticleResource::getResourceName(),
            );

            if (! $blogPage instanceof Page) {
                return $query;
            }

            return $query->orWhereKey($blogPage->getKey());
        };
    }

    #[Override]
    protected function getEditFormSchema(Schema $configurator): array
    {
        return [
            FixedWidthSidebar::make()
                ->mainSchema([
                    $this->getTranslationFormSchema($configurator),
                ])
                ->sidebarSchema(
                    [
                        ...$this->articlePublishPanel($configurator),
                        ...SettingsSchema::make(
                            $configurator,
                            components: [
                                TagsInput::make('tags'),
                            ],
                            pageGroup: $this->articleResourceName(),
                            modifyParentQueryUsing: static::modifyParentQueryUsing($configurator),
                            withParent: false,
                            withType: false,
                        ),
                    ],
                    contained: true,
                ),
            Tabs::make()
                ->columnSpanFull()
                ->tabs($this->getTabs($configurator)),
        ];
    }

    #[Override]
    protected function getTabs(Schema $configurator): array
    {
        return $this->resolvePageTabs($configurator, [
            SettingsTab::make($configurator),
        ]);
    }

    #[Override]
    protected function getEditOptionFormSchema(Schema $configurator): array
    {
        return [
            $this->getTranslationFormSchema($configurator),
            Section::make(__('capell-admin::generic.settings'))
                ->compact()
                ->icon(Heroicon::OutlinedCog6Tooth)
                ->schema([
                    ...SettingsSchema::make(
                        $configurator,
                        components: [
                            TagsInput::make('tags'),
                            MediaLibraryFileUpload::make('image'),
                        ],
                        pageGroup: $this->articleResourceName(),
                        modifyParentQueryUsing: static::modifyParentQueryUsing($configurator),
                        withParent: false,
                        withType: false,
                    ),
                    // The editOption quick-edit modal can't host the full Livewire
                    // panel cleanly, so keep a slim inline publish-date field here.
                    PublishDatesGrid::getVisibleFromField(),
                ]),
        ];
    }

    /**
     * The shared WordPress-style publish panel, pinned to the top of the article
     * editor sidebar. Edit only — on create there is no record to act on yet, so
     * the slim inline publish-date field in PublishSchema covers that case.
     *
     * @return array<int, Livewire>
     */
    protected function articlePublishPanel(Schema $configurator): array
    {
        $record = $configurator->getRecord();

        if ($configurator->getOperation() !== 'edit' || ! $record instanceof Article) {
            return [];
        }

        $key = $record->getKey();

        return [
            Livewire::make(PublishStatusPanel::class, [
                'recordClass' => Article::class,
                'recordId' => is_scalar($key) ? (int) $key : 0,
            ]),
        ];
    }

    #[Override]
    protected function getCreateExtraFor(Schema $configurator): array
    {
        return [
            SiteSelect::make(),
            LayoutSelect::make('layout_id')
                ->reactive(),
            TagsInput::make('tags'),
            PublishSchema::make($configurator),
        ];
    }

    private function articleResourceName(): string
    {
        return ArticleResource::getResourceName();
    }
}
