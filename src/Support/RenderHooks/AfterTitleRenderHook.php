<?php

declare(strict_types=1);

namespace Capell\Blog\Support\RenderHooks;

use Capell\Blog\View\Components\AssetAfterTitle;
use Capell\Frontend\Contracts\RenderHookExtensionInterface;
use Capell\Frontend\Data\RenderHookContext;
use Illuminate\Contracts\View\View;

final class AfterTitleRenderHook implements RenderHookExtensionInterface
{
    public function render(RenderHookContext $context): string
    {
        $item = is_array($context->item) ? $context->item : [];

        $view = resolve(AssetAfterTitle::class, [
            'publishDate' => $item['publishDate'] ?? null,
            'publishDatePosition' => $item['publishDatePosition'] ?? null,
            'tags' => $item['tags'] ?? null,
            'publishDateOutput' => $item['publishDateOutput'] ?? null,
        ])->render();

        return $view instanceof View ? $view->render() : (string) $view;
    }
}
