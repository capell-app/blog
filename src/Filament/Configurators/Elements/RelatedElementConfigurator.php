<?php

declare(strict_types=1);

namespace Capell\Blog\Filament\Configurators\Elements;

use Capell\Admin\Filament\Components\Forms\CacheFrequencySelect;
use Capell\Admin\Filament\Components\Forms\FixedWidthSidebar;
use Capell\Core\Models\Blueprint;
use Capell\LayoutBuilder\Filament\Components\Forms\Element\ComponentSection;
use Capell\LayoutBuilder\Filament\Components\Forms\Element\CreateDetailsSchema;
use Capell\LayoutBuilder\Filament\Components\Forms\Element\DisplaySection;
use Capell\LayoutBuilder\Filament\Components\Forms\Element\ResultsSchema;
use Capell\LayoutBuilder\Filament\Components\Forms\Element\SettingsSchema;
use Capell\LayoutBuilder\Filament\Components\Forms\Element\Tab\ElementAdminTab;
use Capell\LayoutBuilder\Filament\Components\Forms\Element\Tab\ElementDisplayTab;
use Capell\LayoutBuilder\Filament\Components\Forms\Element\TranslationsRepeater;
use Capell\LayoutBuilder\Filament\Configurators\Elements\DefaultElementConfigurator;
use Filament\Forms\Components\Checkbox;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Group;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Tabs;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Override;

class RelatedElementConfigurator extends DefaultElementConfigurator
{
    #[Override]
    public function make(Schema $configurator): array
    {
        $operation = $configurator->getOperation();

        return match ($operation) {
            'createOption', 'editOption', 'replicate' => $this->getOptionSchema($configurator),
            default => $this->getFormSchema($configurator),
        };
    }

    protected function getOptionSchema(Schema $configurator): array
    {
        return [
            CreateDetailsSchema::make($configurator),
            TranslationsRepeater::make($configurator)
                ->contained(fn (string $operation): bool => $operation === 'create'),
            Section::make(__('capell-admin::generic.settings'))
                ->columns()
                ->compact()
                ->icon(Heroicon::OutlinedCog6Tooth)
                ->collapsed()
                ->schema(SettingsSchema::make($configurator)),
        ];
    }

    protected function getFormSchema(Schema $configurator): array
    {
        return [
            CreateDetailsSchema::make($configurator),
            FixedWidthSidebar::make()
                ->mainSchema([
                    TranslationsRepeater::make($configurator),
                ])
                ->sidebarSchema(
                    SettingsSchema::make($configurator),
                    contained: true,
                ),
            Tabs::make()
                ->visibleOn('edit')
                ->columnSpanFull()
                ->tabs([
                    ElementDisplayTab::make([
                        DisplaySection::make([
                            Group::make([
                                Checkbox::make('exclude_parent')
                                    ->label(__('capell-layout-builder::form.exclude_parent')),
                                Select::make('exclude_types')
                                    ->label(__('capell-layout-builder::form.exclude_types'))
                                    ->helperText(__('capell-layout-builder::generic.exclude_types_info'))
                                    ->multiple()
                                    ->options(
                                        function (): array {
                                            /** @var class-string<Blueprint> $model */
                                            $model = Blueprint::class;

                                            return $model::query()
                                                ->pageType()
                                                ->pluck('name', 'key')
                                                ->toArray();
                                        },
                                    ),
                            ]),
                            Grid::make(3)
                                ->schema([
                                    TextInput::make('limit')
                                        ->label(__('capell-layout-builder::form.limit')),
                                    Checkbox::make('pagination')
                                        ->label(__('capell-layout-builder::form.pagination'))
                                        ->default(true),
                                    CacheFrequencySelect::make('cache_frequency'),
                                ]),
                            ...ResultsSchema::make($configurator),
                        ]),
                        ComponentSection::make()
                            ->statePath('meta'),
                    ]),
                    ElementAdminTab::make(),
                ]),
        ];
    }
}
