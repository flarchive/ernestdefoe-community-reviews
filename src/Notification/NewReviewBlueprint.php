<?php

declare(strict_types=1);

namespace ErnestDefoe\CommunityReviews\Notification;

use ErnestDefoe\CommunityReviews\Model\Review;

/** MyBB "X has reviewed Y": to members who reviewed or commented on the item before. */
class NewReviewBlueprint extends Blueprint
{
    public static function getType(): string
    {
        return 'communityReviewsNewReview';
    }

    public static function getSubjectModel(): string
    {
        return Review::class;
    }
}
