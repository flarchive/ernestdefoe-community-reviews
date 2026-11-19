<?php

declare(strict_types=1);

namespace ErnestDefoe\CommunityReviews\Notification;

use Flarum\Database\AbstractModel;
use Flarum\Notification\AlertableInterface;
use Flarum\Notification\Blueprint\BlueprintInterface;
use Flarum\User\User;

/**
 * A notification about a review or a comment. Its subject is that review or comment, so core's notification list
 * drops it as soon as the reader can no longer see it (deleted, held, or the item hidden).
 */
abstract class Blueprint implements BlueprintInterface, AlertableInterface
{
    public function __construct(public readonly AbstractModel $subject, public readonly User $from)
    {
    }

    public function getSubject(): ?AbstractModel
    {
        return $this->subject;
    }

    public function getFromUser(): ?User
    {
        return $this->from;
    }

    public function getData(): mixed
    {
        return null;
    }
}
