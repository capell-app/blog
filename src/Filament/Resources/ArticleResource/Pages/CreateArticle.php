<?php

declare(strict_types=1);

namespace Capell\Blog\Filament\Resources\ArticleResource\Pages;

use Capell\Admin\Enums\ResourceEnum;
use Capell\Admin\Facades\CapellAdmin;
use Capell\Admin\Filament\Resources\PageResource\Pages\CreatePage;
use Capell\Blog\Actions\GetArticleLayoutAction;
use Capell\Blog\Enums\BlogResourceEnum;
use Capell\Blog\Filament\Resources\ArticleResource;
use Capell\Core\Enums\ModelEnum;
use Capell\Core\Facades\CapellCore;

class CreateArticle extends CreatePage
{
    /** @return class-string<ArticleResource> */
    public static function getResource(): string
    {
        return CapellAdmin::getResource(ResourceEnum::Page, BlogResourceEnum::Article->name);
    }

    protected function afterFill(): void
    {
        $this->data['layout_id'] = GetArticleLayoutAction::run()?->id;

        $this->data['type_id'] = CapellCore::getModel(ModelEnum::Type)::pageType()->where('key', 'article')->value('id');
    }
}
