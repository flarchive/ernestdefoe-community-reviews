<?php

declare(strict_types=1);

namespace ErnestDefoe\CommunityReviews\Api\Concerns;

use Illuminate\Database\ConnectionInterface;
use Illuminate\Database\Eloquent\Builder;

/** Every position of a list in one UPDATE (a CASE), for categories and criteria. */
trait Reorders
{
    /**
     * The ids and positions are integers (Write\Clean::ids), so inlining them in the CASE is safe; the column goes
     * through the grammar for table-prefix-safe quoting.
     *
     * @template TModel of \Illuminate\Database\Eloquent\Model
     * @param Builder<TModel> $query
     * @param list<int> $ids
     */
    protected function reorder(Builder $query, ConnectionInterface $db, array $ids): void
    {
        if ($ids === []) {
            return;
        }
        $cases = '';
        foreach ($ids as $position => $id) {
            $cases .= ' WHEN '.(int) $id.' THEN '.(int) $position;
        }
        $query->whereIn('id', $ids)->update([
            'position' => $db->raw('CASE '.$db->getQueryGrammar()->wrap('id').$cases.' END'),
        ]);
    }
}
