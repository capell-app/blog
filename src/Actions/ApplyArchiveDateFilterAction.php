<?php

declare(strict_types=1);

namespace Capell\Blog\Actions;

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
        $publishedAt = $this->publishedAt($query);
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

    /**
     * @template TModel of Model
     *
     * @param  Builder<TModel>  $query
     */
    public function publishedAt(Builder $query): SqlFragment
    {
        $grammar = $query->getQuery()->getGrammar();

        return SqlFragment::raw(
            sprintf(
                'COALESCE(%s, %s)',
                $grammar->wrap('visible_from'),
                $grammar->wrap('created_at'),
            ),
        );
    }
}
