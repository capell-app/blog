<?php

declare(strict_types=1);

namespace Capell\Blog\Filament\Resources\ArticleResource\Pages;

use Capell\Admin\Facades\CapellAdmin;
use Capell\Admin\Filament\Resources\PageResource\Pages\CreatePage;
use Capell\Blog\Filament\Resources\ArticleResource;
use Capell\Core\Facades\CapellCore;

class CreateArticle extends CreatePage
{
    /** @return class-string<ArticleResource> */
    public static function getResource(): string
    {
        return CapellAdmin::getResourcePage('article');
    }

    protected function afterFill(): void
    {
        $this->data['layout_id'] = $this->getArticleLayoutId();

        $this->data['type_id'] = CapellCore::getModel('type')::pageType()->where('key', 'article')->value('id');
    }

    private function getArticleLayoutId(): ?int
    {
        return CapellCore::getModel('layout')::where('key', 'article')->value('id');
    }
}
