<?php

declare(strict_types=1);

namespace ErnestDefoe\CommunityReviews\Listener;

use ErnestDefoe\CommunityReviews\Model\Item;
use ErnestDefoe\CommunityReviews\Model\Review;
use ErnestDefoe\CommunityReviews\Photo\Cleanup;
use Flarum\User\Event\Deleting as UserDeleting;

/** Takes photo files away with the review, item or member they belong to (rows go by cascade). */
class DeletePhotos
{
    public function __construct(private readonly Cleanup $cleanup)
    {
    }

    public function review(Review $review): void
    {
        $this->cleanup->forReviews(Review::query()->whereKey($review->id));
    }

    public function item(Item $item): void
    {
        $this->cleanup->forReviews(Review::query()->where('item_id', $item->id));
    }

    public function user(UserDeleting $event): void
    {
        $this->cleanup->forUser($event->user->id);
    }
}
