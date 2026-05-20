<?php

declare(strict_types=1);

namespace Capell\Blog\Filament\Resources\Articles;

use BackedEnum;
use Capell\Admin\Enums\ConfiguratorTypeEnum;
use Capell\Admin\Filament\Resources\Pages\PageResource;
use Capell\Blog\Actions\GetArticleLayoutAction;
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
use Capell\Core\Models\Blueprint;
use Capell\Core\Models\Language;
use Capell\Core\Models\Site;
use Filament\Support\Icons\Heroicon;
use Illuminate\Contracts\Database\Eloquent\Builder as BuilderContract;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Override;

class ArticleResource extends PageResource
{
    protected static string $adminResourceName = ResourceEnum::Article->name;

    protected static ?int $navigationSort = 2;

    protected static ?string $slug = 'article';

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
        if ($record->site->default) {
            return [];
        }

        return [$record->site->name];
    }

    #[Override]
    public static function mutateFormDataBeforeCreate(array &$data, array $formData = []): void
    {
        $data['layout_id'] = GetArticleLayoutAction::run()?->id;

        /* @var class-string<\Capell\Core\Models\Blueprint> $model */
        $model = Blueprint::class;

        $data['blueprint_id'] = $model::query()
            ->pageType()
            ->where('group', BlogTypeGroupEnum::Article)
            ->value('id');

        $siteId = $data['site_id'] ?? null;

        /* @var class-string<\Capell\Core\Models\Site> $model */
        $model = Site::class;

        $site = $model::query()->find($siteId) ?? $model::query()->default()->first();

        if ($site === null) {
            return;
        }

        if (! isset($data['site_id']) || blank($data['site_id'])) {
            $data['site_id'] = $site->id;
        }

        if ((! isset($data['name']) || blank($data['name'])) && isset($formData['translations'])) {
            $data['name'] = GetNameFromTranslationsAction::run(collect($formData['translations']), $site);
        }
    }

    #[Override]
    public static function applyTypeAdminResourceConstraint(BuilderContract $query, ?bool $hideSystemPages = false): void
    {
        $query->where('group', BlogTypeGroupEnum::Article);
    }
}
