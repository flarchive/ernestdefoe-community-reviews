<?php

declare(strict_types=1);

namespace ErnestDefoe\CommunityReviews\Listener;

use ErnestDefoe\CommunityReviews\Event\CommentSaved;
use ErnestDefoe\CommunityReviews\Event\ReviewSaved;
use ErnestDefoe\CommunityReviews\Job\SendNotifications;
use Illuminate\Contracts\Queue\Queue;

/** Hands a committed review or comment to the queue for its notifications (Job\SendNotifications). */
class QueueNotifications
{
    public function __construct(private readonly Queue $queue)
    {
    }

    public function review(ReviewSaved $event): void
    {
        $this->queue->push(new SendNotifications($event->isNew ? 'review' : 'edit', $event->review->id, $event->actor->id, $event->previousMerchantId));
    }

    public function comment(CommentSaved $event): void
    {
        $this->queue->push(new SendNotifications('comment', $event->comment->id, $event->actor->id));
    }
}
