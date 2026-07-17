<?php

declare(strict_types=1);

namespace Capell\Blog\Filament\Resources\Articles\Pages;

use Capell\Admin\Enums\ResourceEnum as AdminResourceEnum;
use Capell\Admin\Filament\Resources\Pages\Pages\CreatePage;
use Capell\Admin\Support\AdminSurfaceLookup;
use Capell\Blog\Actions\GetArticleLayoutAction;
use Capell\Blog\Enums\BlogPageTypeEnum;
use Capell\Blog\Enums\ResourceEnum;
use Capell\Blog\Filament\Resources\Articles\ArticleResource;
use Capell\Core\Models\Blueprint;
use Capell\Core\Models\Layout;
use Override;

class CreateArticle extends CreatePage
{
    #[Override]
    public static function getResource(): string
    {
        return AdminSurfaceLookup::resourceIfRegistered(AdminResourceEnum::Page, strtolower(ResourceEnum::Article->name))
            ?? ArticleResource::class;
    }

    #[Override]
    protected function beforeFill(): void
    {
        parent::beforeFill();

        $articleLayout = GetArticleLayoutAction::run();
        $this->data['layout_id'] = $articleLayout instanceof Layout ? $articleLayout->getKey() : null;

        /** @var class-string<Blueprint> $model */
        $model = Blueprint::class;

        $this->data['blueprint_id'] = $model::query()
            ->pageType()
            ->where('key', BlogPageTypeEnum::Article->value)
            ->value('id');
    }
}
