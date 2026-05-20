<?php

declare(strict_types=1);

namespace Capell\Blog\Filament\Components\Forms\Article;

use Capell\Core\Enums\BlueprintSubjectEnum;
use Capell\Tags\Filament\Components\Forms\TagsInput as BaseTagsInput;

class TagsInput extends BaseTagsInput
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->type(BlueprintSubjectEnum::Page->value);
    }
}
