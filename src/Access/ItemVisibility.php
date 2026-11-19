<?php

declare(strict_types=1);

namespace ErnestDefoe\CommunityReviews\Access;

use Flarum\User\User;
use Illuminate\Database\Eloquent\Builder;

/**
 * Who sees an item. One rule for the API, the item page's head tags, the photo files, notifications and
 * search: moderators see everything; everyone with `communityReviews.view` sees approved items; a member
 * also sees the items they added that are still waiting for approval.
 */
class ItemVisibility
{
    public function __invoke(User $actor, Builder $query): void
    {
        if ($actor->hasPermission('communityReviews.moderate')) {
            return;
        }

        $query->where(function (Builder $q) use ($actor): void {
            if ($actor->hasPermission('communityReviews.view')) {
                $q->where('community_review_items.status', 'approved');
            } else {
                // Without the view permission: no approved items at all (their own may still follow below).
                $q->whereRaw('1 = 0');
            }
            if (! $actor->isGuest()) {
                $q->orWhere('community_review_items.user_id', $actor->id);
            }
        });
    }
}
