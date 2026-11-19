<?php

declare(strict_types=1);

namespace ErnestDefoe\CommunityReviews\Access;

use ErnestDefoe\CommunityReviews\Model\Item;
use Flarum\User\Access\AbstractPolicy;
use Flarum\User\User;

/** What a member may do to an item they can already see (seeing is ItemVisibility's job). */
class ItemPolicy extends AbstractPolicy
{
    use Ownership;

    public function edit(User $actor, Item $item): ?string
    {
        return $this->mayEdit($actor, $item->user_id) ? $this->allow() : $this->deny();
    }

    public function delete(User $actor, Item $item): ?string
    {
        return $this->mayDelete($actor, $item->user_id) ? $this->allow() : $this->deny();
    }

    /** Merge into another item, approve. */
    public function moderate(User $actor, Item $item): ?string
    {
        return $actor->hasPermission('communityReviews.moderate') ? $this->allow() : $this->deny();
    }

    public function review(User $actor, Item $item): ?string
    {
        return ! $actor->isGuest() && $actor->hasPermission('communityReviews.write')
            && ($item->isApproved() || $this->owns($actor, $item->user_id))
            ? $this->allow() : $this->deny();
    }

    public function comment(User $actor, Item $item): ?string
    {
        return ! $actor->isGuest() && $actor->hasPermission('communityReviews.comment') && $item->isApproved() ? $this->allow() : $this->deny();
    }

    public function report(User $actor, Item $item): ?string
    {
        return ! $actor->isGuest() ? $this->allow() : $this->deny();
    }
}
