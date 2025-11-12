<?php

declare(strict_types=1);

namespace Capell\Blog\Filament\Components\Forms\Page;

use Capell\Blog\Filament\Components\Forms\TagsInput;
use Capell\Core\Enums\TypeEnum;
use Capell\Core\Models\Page;

class PageTagsInput extends TagsInput
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->type(TypeEnum::Page->value)
            ->visible(
                fn (string $operation, ?Page $record): bool => in_array($operation, ['edit', 'editOption'], true),
            );
    }
}
