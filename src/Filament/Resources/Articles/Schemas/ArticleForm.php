<?php

declare(strict_types=1);

namespace Capell\Blog\Filament\Resources\Articles\Schemas;

use Capell\Admin\Data\Configurators\ConfiguratorContextData;
use Capell\Admin\Enums\ConfiguratorTypeEnum;
use Capell\Admin\Filament\Contracts\FormConfigurator;
use Capell\Admin\Support\Configurators\ConfiguratorResolver;
use Capell\Blog\Actions\ResolveEligibleArticleBlueprintAction;
use Capell\Blog\Enums\BlogTypeGroupEnum;
use Capell\Blog\Filament\Configurators\Articles\ArticlePageConfigurator;
use Capell\Blog\Filament\Resources\Articles\ArticleResource;
use Capell\Core\Contracts\Pageable;
use Capell\Core\Models\Blueprint;
use Filament\Forms\Components\Hidden;
use Filament\Schemas\Components\Text;
use Filament\Schemas\Schema;

class ArticleForm implements FormConfigurator
{
    public static function configure(Schema $configurator, ?ConfiguratorContextData $context = null): Schema
    {
        $resourceName = ArticleResource::getResourceName();
        $resolver = resolve(ConfiguratorResolver::class);
        $record = $configurator->getRecord();

        if ($record instanceof Pageable && $record->blueprint_id !== null) {
            /** @var class-string<Blueprint> $model */
            $model = Blueprint::class;

            $type = $model::query()->find($record->blueprint_id);
            $adminType = $type instanceof Blueprint
                ? $resolver->resolveForType($type, ConfiguratorTypeEnum::Page, ArticlePageConfigurator::getKey())
                : ArticlePageConfigurator::class;

            $record->loadMissing('blueprint');

            return $adminType::configure($configurator, ConfiguratorContextData::forEdit(ConfiguratorTypeEnum::Page));
        }

        // A blueprint disabled while this form is open must still render its
        // schema. Mount and persistence validate eligibility independently.
        $defaultType = $context?->typeKey !== null
            ? Blueprint::query()->pageType()->where('group', BlogTypeGroupEnum::Article)
                ->where('key', $context->typeKey)->first()
            : null;
        $defaultType ??= resolve(ResolveEligibleArticleBlueprintAction::class)->handle(key: $context?->typeKey);

        $adminType = $resolver->resolveForType($defaultType, ConfiguratorTypeEnum::Page, ArticlePageConfigurator::getKey());
        $operation = $configurator->getOperation();

        $configurator = $adminType::configure($configurator, new ConfiguratorContextData(
            ConfiguratorTypeEnum::Page,
            in_array($operation, ['create', 'createOption', 'edit', 'editOption', 'replicate'], true) ? $operation : 'create',
            $defaultType->key,
            $resourceName,
        ));

        return $configurator->components([
            Text::make(__('capell-blog::generic.article_blueprint_unavailable'))->visible(! $defaultType->status),
            Hidden::make('blueprint_id')->default($defaultType->id)
                ->required()->in([$defaultType->id]),
            ...$configurator->getComponents(withHidden: true),
        ]);
    }
}
