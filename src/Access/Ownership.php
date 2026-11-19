<?php

declare(strict_types=1);

namespace ErnestDefoe\CommunityReviews\Access;

use Flarum\User\User;

/**
 * MyBB's three group settings, as permissions: edit own content, delete own content, moderate (edit and delete
 * anyone's). Shared by the item, review and comment policies.
 */
trait Ownership
{
    protected function owns(User $actor, ?int $ownerId): bool
    {
        return ! $actor->isGuest() && $ownerId !== null && $ownerId === $actor->id;
    }

    protected function mayEdit(User $actor, ?int $ownerId): bool
    {
        return $actor->hasPermission('communityReviews.moderate')
            || ($this->owns($actor, $ownerId) && $actor->hasPermission('communityReviews.editOwn'));
    }

    protected function mayDelete(User $actor, ?int $ownerId): bool
    {
        return $actor->hasPermission('communityReviews.moderate')
            || ($this->owns($actor, $ownerId) && $actor->hasPermission('communityReviews.deleteOwn'));
    }
}
