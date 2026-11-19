<?php

declare(strict_types=1);

namespace ErnestDefoe\CommunityReviews\Access;

use ErnestDefoe\CommunityReviews\Model\Item;
use Flarum\User\User;
use Illuminate\Database\Eloquent\Builder;

/** Who sees a comment: whoever sees its item. */
class CommentVisibility
{
    public function __invoke(User $actor, Builder $query): void
    {
        if ($actor->hasPermission('communityReviews.moderate')) {
            return;
        }

        $query->whereIn('community_review_comments.item_id', Item::whereVisibleTo($actor)->select('community_review_items.id'));
    }
}
