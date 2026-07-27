<?php

declare(strict_types=1);

namespace Capell\Blog\Actions;

use Illuminate\Contracts\Database\Eloquent\Builder as BuilderContract;
use Lorisleiva\Actions\Concerns\AsFake;
use Lorisleiva\Actions\Concerns\AsObject;

/**
 * @method static BuilderContract run(BuilderContract $query, int $languageId)
 */
final class ApplyPreferredLanguageOrderAction
{
    use AsFake;
    use AsObject;

    public function handle(BuilderContract $query, int $languageId): BuilderContract
    {
        return $query->orderByRaw(
            'CASE WHEN language_id = ? THEN 0 ELSE 1 END',
            [$languageId],
        );
    }
}
