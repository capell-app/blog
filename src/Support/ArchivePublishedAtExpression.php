<?php

declare(strict_types=1);

namespace Capell\Blog\Support;

use Capell\Core\Data\Database\SqlFragment;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

final class ArchivePublishedAtExpression
{
    /**
     * @template TModel of Model
     *
     * @param  Builder<TModel>  $query
     */
    public static function for(Builder $query): SqlFragment
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
