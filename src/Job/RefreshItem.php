<?php

declare(strict_types=1);

namespace ErnestDefoe\CommunityReviews\Job;

use ErnestDefoe\CommunityReviews\Aggregates;
use Flarum\Queue\AbstractJob;

/** Recounts one item (and its category) after a review, comment, photo or merge changed it. */
class RefreshItem extends AbstractJob
{
    public function __construct(public readonly int $itemId)
    {
    }

    public function handle(Aggregates $aggregates): void
    {
        $aggregates->refreshItem($this->itemId);
    }
}
