<?php

declare(strict_types=1);

namespace Capell\Blog\Filament\Resources\Articles\Pages;

use Capell\Admin\Filament\Resources\Pages\Pages\EditPage;
use Capell\Blog\Filament\Resources\Articles\ArticleResource;
use Filament\Resources\Pages\Concerns\InteractsWithRecord;
use Illuminate\Database\Eloquent\Model;
use Override;

class EditArticle extends EditPage
{
    use InteractsWithRecord { resolveRecord as baseResolveRecord; }

    #[Override]
    public static function getResource(): string
    {
        return ArticleResource::class;
    }

    #[Override]
    public static function authorizeResourceAccess(): void
    {
        abort_unless(ArticleResource::canAccess(), 403);
    }

    #[Override]
    public static function canAccess(array $parameters = []): bool
    {
        $record = $parameters['record'] ?? null;

        if ($record instanceof Model) {
            return ArticleResource::canEdit($record);
        }

        return ArticleResource::canAccess();
    }

    #[Override]
    protected function authorizeAccess(): void
    {
        abort_unless(ArticleResource::canEdit($this->getRecord()), 403);
    }
}
