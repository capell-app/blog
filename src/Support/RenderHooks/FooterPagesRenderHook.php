<?php

declare(strict_types=1);

namespace Capell\Blog\Support\RenderHooks;

use Capell\Blog\View\Components\Footer\Pages;
use Capell\Core\Contracts\Rendering\RenderHookExtensionInterface;
use Capell\Core\Data\Rendering\RenderHookContext;
use Illuminate\Contracts\View\View;

final class FooterPagesRenderHook implements RenderHookExtensionInterface
{
    public function render(RenderHookContext $context): string
    {
        $view = resolve(Pages::class, [
            'item' => $context->item,
        ])->render();

        return $view instanceof View ? $view->render() : (string) $view;
    }
}
