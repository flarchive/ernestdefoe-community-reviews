<?php

declare(strict_types=1);

namespace ErnestDefoe\CommunityReviews\Notification;

use ErnestDefoe\CommunityReviews\Model\Review;

/** MyBB "X has tagged you in a review of Y": the merchant a review names. */
class MerchantTaggedBlueprint extends Blueprint
{
    public static function getType(): string
    {
        return 'communityReviewsMerchantTagged';
    }

    public static function getSubjectModel(): string
    {
        return Review::class;
    }
}
