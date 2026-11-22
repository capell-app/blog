<?php

declare(strict_types=1);

namespace Capell\Blog\Support\RenderHooks;

use Capell\Blog\View\Components\Page\BeforeContentTags;
use Capell\Core\Contracts\Rendering\RenderHookExtensionInterface;
use Capell\Core\Data\Rendering\RenderHookContext;
use Illuminate\Contracts\View\View;

final class BeforeContentTagsRenderHook implements RenderHookExtensionInterface
{
    public function render(RenderHookContext $context): string
    {
        $item = is_array($context->item) ? $context->item : [];

        $view = resolve(BeforeContentTags::class, [
            'item' => $item,
            'tags' => $item['tags'] ?? null,
        ])->render();

        return $view instanceof View ? $view->render() : (string) $view;
    }
}
