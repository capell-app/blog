<?php

declare(strict_types=1);

namespace Capell\Blog\Filament\Resources\Articles\Pages;

use Capell\Admin\Enums\ResourceEnum as AdminResourceEnum;
use Capell\Admin\Filament\Actions\ImportHeaderAction;
use Capell\Admin\Filament\Resources\Pages\Pages\ListPages;
use Capell\Admin\Support\AdminSurfaceLookup;
use Capell\Admin\Support\Schemas\AdminSchemaExtensionPipeline;
use Capell\Blog\Actions\ResolveEligibleArticleBlueprintAction;
use Capell\Blog\Enums\ResourceEnum;
use Capell\Blog\Filament\Resources\Articles\ArticleResource;
use Filament\Actions\Action;
use Filament\Forms\Components\Select;
use Illuminate\Contracts\Support\Htmlable;
use Override;

class ListArticles extends ListPages
{
    #[Override]
    public static function getResource(): string
    {
        return AdminSurfaceLookup::resourceIfRegistered(AdminResourceEnum::Page, strtolower(ResourceEnum::Article->name))
            ?? ArticleResource::class;
    }

    public function newArticleAction(): Action
    {
        $resolver = resolve(ResolveEligibleArticleBlueprintAction::class);
        $choices = $resolver->query()->pluck('name', 'id');
        $resource = static::getResource();

        return Action::make('create')
            ->label(__('capell-blog::generic.new_article'))
            ->icon('heroicon-o-document-plus')
            ->visible(fn (): bool => $resource::canCreate())
            ->disabled($choices->isEmpty())
            ->tooltip($choices->isEmpty() ? (string) __('capell-blog::generic.article_blueprint_unavailable') : null)
            ->url($choices->count() === 1
                ? $resource::getUrl('create', ['type' => $resolver->handle($choices->keys()->first())->key])
                : null)
            ->schema([
                Select::make('blueprint_id')
                    ->label(__('capell-admin::table.blueprint'))
                    ->options($choices)
                    ->required(),
            ])
            ->action(function (array $data) use ($resolver, $resource): void {
                abort_unless($resource::canCreate(), 403);
                $blueprint = $resolver->handle($data['blueprint_id'] ?? null);
                $this->redirect($resource::getUrl('create', ['type' => $blueprint->key]));
            });
    }

    #[Override]
    public function getSubheading(): string|Htmlable|null
    {
        return __('capell-blog::generic.articles_info');
    }

    #[Override]
    protected function getActions(): array
    {
        return [
            $this->newArticleAction(),
            ImportHeaderAction::make(static::class),
            ...resolve(AdminSchemaExtensionPipeline::class)->resourceHeaderActions(static::class),
        ];
    }
}
