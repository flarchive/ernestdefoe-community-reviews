<?php

declare(strict_types=1);

namespace ErnestDefoe\CommunityReviews\Access;

use ErnestDefoe\CommunityReviews\Model\Item;
use Flarum\User\User;
use Illuminate\Database\Eloquent\Builder;

/**
 * Who sees a review: whoever sees its item, once the review is approved (always, unless the approval queue
 * is on); its author at any stage; moderators always.
 */
class ReviewVisibility
{
    public function __invoke(User $actor, Builder $query): void
    {
        if ($actor->hasPermission('communityReviews.moderate')) {
            return;
        }

        $query->where(function (Builder $q) use ($actor): void {
            $q->where('community_reviews.status', 'approved')
                ->whereIn('community_reviews.item_id', Item::whereVisibleTo($actor)->select('community_review_items.id'));
            if (! $actor->isGuest()) {
                $q->orWhere('community_reviews.user_id', $actor->id);
            }
        });
    }
}
