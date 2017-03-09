<?php

declare(strict_types=1);

namespace Capell\Blog\Filament\Resources\Articles\Pages;

use Capell\Admin\Enums\ResourceEnum as AdminResourceEnum;
use Capell\Admin\Filament\Resources\Pages\Pages\CreatePage;
use Capell\Admin\Support\AdminSurfaceLookup;
use Capell\Blog\Actions\GetArticleLayoutAction;
use Capell\Blog\Actions\ResolveEligibleArticleBlueprintAction;
use Capell\Blog\Enums\ResourceEnum;
use Capell\Blog\Filament\Resources\Articles\ArticleResource;
use Capell\Core\Models\Layout;
use Capell\Core\Models\Page;
use Illuminate\Validation\ValidationException;
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
    public function mount(?Page $record = null): void
    {
        $this->authorizeAccess();

        try {
            $this->type = resolve(ResolveEligibleArticleBlueprintAction::class)->handle(key: $this->type)->key;
        } catch (ValidationException) {
            abort(404);
        }

        parent::mount($record);
    }

    #[Override]
    protected function beforeFill(): void
    {
        parent::beforeFill();

        $articleLayout = GetArticleLayoutAction::run();
        $this->data['layout_id'] = $articleLayout instanceof Layout ? $articleLayout->getKey() : null;

        $blueprint = resolve(ResolveEligibleArticleBlueprintAction::class)->handle(key: $this->type);
        $this->type = $blueprint->key;
        $this->data['blueprint_id'] = $blueprint->getKey();
    }

    #[Override]
    protected function fillForm(): void
    {
        $this->callHook('beforeFill');
        $this->form->fill($this->data);
        $this->callHook('afterFill');
    }
}
