<?php

declare(strict_types=1);

namespace ErnestDefoe\CommunityReviews\Access;

use ErnestDefoe\CommunityReviews\Model\Comment;
use Flarum\User\Access\AbstractPolicy;
use Flarum\User\User;

class CommentPolicy extends AbstractPolicy
{
    use Ownership;

    public function edit(User $actor, Comment $comment): ?string
    {
        return $this->mayEdit($actor, $comment->user_id) ? $this->allow() : $this->deny();
    }

    public function delete(User $actor, Comment $comment): ?string
    {
        return $this->mayDelete($actor, $comment->user_id) ? $this->allow() : $this->deny();
    }

    public function report(User $actor, Comment $comment): ?string
    {
        return ! $actor->isGuest() ? $this->allow() : $this->deny();
    }
}
