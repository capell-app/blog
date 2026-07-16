<?php

declare(strict_types=1);

namespace Capell\Blog\Actions;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Lorisleiva\Actions\Concerns\AsFake;
use Lorisleiva\Actions\Concerns\AsObject;

/**
 * Applies year/month archive filtering to an article query, branching on the
 * database driver because SQLite and MySQL expose different date functions.
 * Extracted from the Archive Livewire component to keep DB-portability logic
 * out of the frontend layer.
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
        if (DB::getDriverName() === 'sqlite') {
            return $this->applySqlite($query, $year, $month);
        }

        return $this->applyMysql($query, $year, $month);
    }

    /**
     * @param  Builder<Model>  $query
     * @return Builder<Model>
     */
    private function applySqlite(Builder $query, ?int $year, ?int $month): Builder
    {
        return $query
            ->when(
                $year,
                fn (Builder $query): Builder => $query->whereRaw(
                    "strftime('%Y', COALESCE(`visible_from`, `created_at`)) = ?",
                    [(string) $year],
                ),
            )
            ->when(
                $month,
                fn (Builder $query): Builder => $query->whereRaw(
                    "strftime('%m', COALESCE(`visible_from`, `created_at`)) = ?",
                    [str_pad((string) $month, 2, '0', STR_PAD_LEFT)],
                ),
            );
    }

    /**
     * @param  Builder<Model>  $query
     * @return Builder<Model>
     */
    private function applyMysql(Builder $query, ?int $year, ?int $month): Builder
    {
        return $query
            ->when(
                $year,
                fn (Builder $query): Builder => $query->whereRaw(
                    'YEAR(COALESCE(`visible_from`, `created_at`)) = ?',
                    [$year],
                ),
            )
            ->when(
                $month,
                fn (Builder $query): Builder => $query->whereRaw(
                    'MONTH(COALESCE(`visible_from`, `created_at`)) = ?',
                    [$month],
                ),
            );
    }
}
