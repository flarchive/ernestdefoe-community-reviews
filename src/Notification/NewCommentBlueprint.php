<?php

declare(strict_types=1);

namespace ErnestDefoe\CommunityReviews\Notification;

use ErnestDefoe\CommunityReviews\Model\Comment;

/** MyBB "X has commented on Y": to members who reviewed or commented on the item before. */
class NewCommentBlueprint extends Blueprint
{
    public static function getType(): string
    {
        return 'communityReviewsNewComment';
    }

    public static function getSubjectModel(): string
    {
        return Comment::class;
    }
}
