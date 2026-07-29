<?php

declare(strict_types=1);

namespace Capell\Blog\Support\RenderHooks;

use Capell\Blog\View\Components\ArticleMeta;
use Capell\Frontend\Contracts\RenderHookExtensionInterface;
use Capell\Frontend\Data\RenderHookContext;
use Illuminate\Contracts\View\View;

final class ArticleMetaRenderHook implements RenderHookExtensionInterface
{
    public function render(RenderHookContext $context): string
    {
        $item = is_array($context->item) ? $context->item : [];

        $view = resolve(ArticleMeta::class, [
            'item' => $context->item,
            'withAuthor' => $item['withAuthor'] ?? false,
            'author' => $item['author'] ?? null,
            'articleMetaData' => $item['articleMetaData'] ?? null,
        ])->render();

        return $view instanceof View ? $view->render() : (string) $view;
    }
}
