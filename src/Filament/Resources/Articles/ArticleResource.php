<?php

declare(strict_types=1);

namespace Capell\Blog\Filament\Resources\Articles;

use BackedEnum;
use Capell\Admin\Enums\ConfiguratorTypeEnum;
use Capell\Admin\Filament\Resources\Pages\PageResource;
use Capell\Admin\Filament\Resources\Pages\RelationManagers\ChildrenRelationManager;
use Capell\Admin\Filament\Resources\Pages\RelationManagers\SiblingsRelationManager;
use Capell\Blog\Actions\GetArticleLayoutAction;
use Capell\Blog\Actions\ResolveArticleCreateSiteAction;
use Capell\Blog\Actions\ResolveEligibleArticleBlueprintAction;
use Capell\Blog\Enums\BlogTypeGroupEnum;
use Capell\Blog\Enums\ResourceEnum;
use Capell\Blog\Filament\Resources\Articles\Pages\CreateArticle;
use Capell\Blog\Filament\Resources\Articles\Pages\EditArticle;
use Capell\Blog\Filament\Resources\Articles\Pages\ListArticles;
use Capell\Blog\Filament\Resources\Articles\Schemas\ArticleForm;
use Capell\Blog\Filament\Resources\Articles\Tables\ArticlePagesTable;
use Capell\Blog\Models\Article;
use Capell\Blog\Providers\BlogServiceProvider;
use Capell\Blog\Support\Loader\BlogLoader;
use Capell\Core\Actions\GetNameFromTranslationsAction;
use Capell\Core\Facades\CapellCore;
use Capell\Core\Models\Language;
use Capell\Core\Models\Layout;
use Capell\Core\Models\Site;
use Filament\Support\Icons\Heroicon;
use Illuminate\Contracts\Database\Eloquent\Builder as BuilderContract;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use Override;

class ArticleResource extends PageResource
{
    protected static ?string $slug = 'blog/article';

    protected static string $adminResourceName = ResourceEnum::Article->name;

    protected static ?int $navigationSort = 2;

    protected static bool $isGloballySearchable = true;

    protected static string $tableConfigurator = ArticlePagesTable::class;

    protected static string $formConfigurator = ArticleForm::class;

    /**
     * @return class-string<Article>
     */
    #[Override]
    public static function getModel(): string
    {
        return Article::class;
    }

    public static function getResourceType(): ConfiguratorTypeEnum
    {
        return ConfiguratorTypeEnum::Page;
    }

    public static function getBasePath(Site $site, Language $language): string
    {
        return BlogLoader::getBlogPageUrl($site, $language, fullUrl: false) . '/';
    }

    #[Override]
    public static function getLabel(): string
    {
        return __('capell-blog::generic.article');
    }

    #[Override]
    public static function getNavigationIcon(): string|BackedEnum|Htmlable|null
    {
        return Heroicon::OutlinedNewspaper;
    }

    #[Override]
    public static function getActiveNavigationIcon(): string|BackedEnum|Htmlable|null
    {
        return Heroicon::Newspaper;
    }

    #[Override]
    public static function getNavigationLabel(): string
    {
        return (string) (__('capell-blog::generic.articles'));
    }

    #[Override]
    public static function getResourceName(): string
    {
        return strtolower(ResourceEnum::Article->name);
    }

    #[Override]
    public static function getNavigationParentItem(): ?string
    {
        return null;
    }

    #[Override]
    public static function shouldRegisterNavigation(): bool
    {
        return CapellCore::getPackage(BlogServiceProvider::$packageName)->isInstalled();
    }

    #[Override]
    public static function getPages(): array
    {
        return [
            'index' => ListArticles::route('/'),
            'create' => CreateArticle::route('/create'),
            'edit' => EditArticle::route('/{record}/edit'),
        ];
    }

    #[Override]
    public static function getRelations(): array
    {
        $hierarchyManagers = [
            ChildrenRelationManager::class,
            SiblingsRelationManager::class,
        ];

        return array_values(array_filter(
            parent::getRelations(),
            static fn (mixed $manager): bool => ! is_string($manager) || ! in_array($manager, $hierarchyManagers, true),
        ));
    }

    #[Override]
    public static function getPluralModelLabel(): string
    {
        return __('capell-blog::generic.articles');
    }

    #[Override]
    public static function getGlobalSearchEloquentQuery(): Builder
    {
        return static::getEloquentQuery()
            ->with([
                'site:id,name,default',
                'type:id,name',
            ]);
    }

    #[Override]
    public static function getGlobalSearchResultDetails(Model $record): array
    {
        if (! $record instanceof Article || ! $record->site instanceof Site || $record->site->default) {
            return [];
        }

        return [$record->site->name];
    }

    #[Override]
    public static function mutateFormDataBeforeCreate(array &$data, array $formData = []): void
    {
        $data['order'] ??= 0;

        $articleLayout = GetArticleLayoutAction::run();
        $data['layout_id'] = $articleLayout instanceof Layout ? $articleLayout->getKey() : null;

        $data['blueprint_id'] = resolve(ResolveEligibleArticleBlueprintAction::class)
            ->handle($data['blueprint_id'] ?? $formData['blueprint_id'] ?? null)->getKey();

        $site = ResolveArticleCreateSiteAction::run($data['site_id'] ?? null);
        $data['site_id'] = $site->getKey();

        if ((! isset($data['name']) || blank($data['name'])) && isset($formData['translations'])) {
            $data['name'] = GetNameFromTranslationsAction::run(new Collection($formData['translations']), $site);
        }
    }

    #[Override]
    public static function applyTypeAdminResourceConstraint(BuilderContract $query, ?bool $hideSystemPages = false): void
    {
        $query->where('group', BlogTypeGroupEnum::Article);
    }
}
