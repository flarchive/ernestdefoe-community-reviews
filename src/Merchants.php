<?php

declare(strict_types=1);

namespace ErnestDefoe\CommunityReviews;

use Flarum\User\User;
use Illuminate\Database\Eloquent\Builder;

/**
 * MyBB's "Merchants group": the members a reviewer may tag as where they bought the item.
 */
class Merchants
{
    public function __construct(private readonly Config $config)
    {
    }

    /** @return Builder<User>|null */
    public function query(): ?Builder
    {
        $group = $this->config->merchantGroupId();

        return $group === null ? null : User::query()->whereHas('groups', fn (Builder $q) => $q->where('groups.id', $group));
    }

    /** A merchant by username (what the picker sends) or by id. */
    public function find(string $nameOrId): ?User
    {
        $query = $this->query();
        if ($query === null) {
            return null;
        }

        return ctype_digit($nameOrId)
            ? $query->whereKey((int) $nameOrId)->first()
            : $query->where('username', $nameOrId)->first();
    }
}
