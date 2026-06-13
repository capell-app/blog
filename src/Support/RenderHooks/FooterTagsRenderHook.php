<?php

declare(strict_types=1);

namespace Capell\Blog\Support\RenderHooks;

use Capell\Blog\View\Components\Footer\Tags;
use Capell\Frontend\Contracts\RenderHookExtensionInterface;
use Capell\Frontend\Data\RenderHookContext;
use Illuminate\Contracts\View\View;

final class FooterTagsRenderHook implements RenderHookExtensionInterface
{
    public function render(RenderHookContext $context): string
    {
        $view = resolve(Tags::class, [
            'item' => $context->item,
        ])->render();

        return $view instanceof View ? $view->render() : '';
    }
}
