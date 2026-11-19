<?php

declare(strict_types=1);

namespace ErnestDefoe\CommunityReviews\Event;

use ErnestDefoe\CommunityReviews\Model\Review;
use Flarum\User\User;

/** A review was created or edited and committed. Notifications and Renown listen. */
class ReviewSaved
{
    public function __construct(
        public readonly Review $review,
        public readonly User $actor,
        public readonly bool $isNew,
        public readonly ?int $previousMerchantId,
    ) {
    }
}
