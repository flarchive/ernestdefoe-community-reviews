<?php

declare(strict_types=1);

namespace ErnestDefoe\CommunityReviews\Access;

use ErnestDefoe\CommunityReviews\Config;
use ErnestDefoe\CommunityReviews\Model\Review;
use Flarum\User\Access\AbstractPolicy;
use Flarum\User\User;

class ReviewPolicy extends AbstractPolicy
{
    use Ownership;

    public function __construct(private readonly Config $config)
    {
    }

    public function edit(User $actor, Review $review): ?string
    {
        return $this->mayEdit($actor, $review->user_id) ? $this->allow() : $this->deny();
    }

    public function delete(User $actor, Review $review): ?string
    {
        return $this->mayDelete($actor, $review->user_id) ? $this->allow() : $this->deny();
    }

    /** Approve a held review, mark its author a verified owner. */
    public function moderate(User $actor, Review $review): ?string
    {
        return $actor->hasPermission('communityReviews.moderate') ? $this->allow() : $this->deny();
    }

    /** "Helpful" (an extra): members, not on their own review, only while the switch is on. */
    public function vote(User $actor, Review $review): ?string
    {
        return $this->config->extra('helpful') && ! $actor->isGuest() && ! $this->owns($actor, $review->user_id)
            && $actor->hasPermission('communityReviews.vote') && $review->isApproved()
            ? $this->allow() : $this->deny();
    }

    public function report(User $actor, Review $review): ?string
    {
        return ! $actor->isGuest() ? $this->allow() : $this->deny();
    }
}
