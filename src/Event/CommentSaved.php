<?php

declare(strict_types=1);

namespace ErnestDefoe\CommunityReviews\Event;

use ErnestDefoe\CommunityReviews\Model\Comment;
use Flarum\User\User;

/** A new comment was committed. */
class CommentSaved
{
    public function __construct(
        public readonly Comment $comment,
        public readonly User $actor,
    ) {
    }
}
