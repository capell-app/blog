<?php

declare(strict_types=1);

namespace Capell\Blog\Actions;

use Capell\Blog\Support\ArchivePublishedAtExpression;
use Capell\Core\Data\Database\SqlFragment;
use Capell\Core\Enums\Database\DatabaseDateOperation;
use Capell\Core\Facades\CapellDatabase;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Lorisleiva\Actions\Concerns\AsFake;
use Lorisleiva\Actions\Concerns\AsObject;

/**
 * Applies year/month archive filtering through Core's active database dialect.
 *
 * @method static Builder<Model> run(Builder<Model> $query, ?int $year, ?int $month)
 */
class ApplyArchiveDateFilterAction
{
    use AsFake;
    use AsObject;

    /**
     * @param  Builder<Model>  $query
     * @return Builder<Model>
     */
    public function handle(Builder $query, ?int $year, ?int $month): Builder
    {
        $publishedAt = ArchivePublishedAtExpression::for($query);
        $dialect = CapellDatabase::for($query->getModel())->queryDialect();

        return $query
            ->when(
                $year,
                function (Builder $query) use ($dialect, $publishedAt, $year): Builder {
                    $fragment = $dialect->date(DatabaseDateOperation::Year, $publishedAt);
                    $where = new SqlFragment($fragment->sql . ' = ?', [...$fragment->bindings, $year]);
                    $where->applyWhere($query->getQuery());

                    return $query;
                },
            )
            ->when(
                $month,
                function (Builder $query) use ($dialect, $publishedAt, $month): Builder {
                    $fragment = $dialect->date(DatabaseDateOperation::Month, $publishedAt);
                    $where = new SqlFragment($fragment->sql . ' = ?', [...$fragment->bindings, $month]);
                    $where->applyWhere($query->getQuery());

                    return $query;
                },
            );
    }
}
