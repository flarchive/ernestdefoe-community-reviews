<?php

declare(strict_types=1);

namespace ErnestDefoe\CommunityReviews\Job;

use ErnestDefoe\CommunityReviews\Model\Comment;
use ErnestDefoe\CommunityReviews\Model\Item;
use ErnestDefoe\CommunityReviews\Model\Review;
use ErnestDefoe\CommunityReviews\Notification\MerchantTaggedBlueprint;
use ErnestDefoe\CommunityReviews\Notification\NewCommentBlueprint;
use ErnestDefoe\CommunityReviews\Notification\NewReviewBlueprint;
use Flarum\Group\Group;
use Flarum\Notification\NotificationSyncer;
use Flarum\Queue\AbstractJob;
use Flarum\User\User;
use Illuminate\Database\ConnectionInterface;
use Illuminate\Database\Query\Builder as QueryBuilder;

/**
 * MyBB's three MyAlerts, off the request:
 *
 * - the merchant a review newly tags ("X has tagged you in a review of Y");
 * - members who reviewed or commented on the item before ("X has reviewed Y" / "X has commented on Y").
 *
 * Only for an approved review on an approved item, and only to members who can see it (asked of the visibility
 * permission set-wise: one query for the whole list). The list is capped, so a very busy item can't fan out forever.
 */
class SendNotifications extends AbstractJob
{
    private const CAP = 200;

    public function __construct(
        private readonly string $kind,
        private readonly int $id,
        private readonly int $actorId,
        private readonly ?int $previousMerchantId = null,
    ) {
    }

    public function handle(NotificationSyncer $notifications, ConnectionInterface $db): void
    {
        $actor = User::query()->find($this->actorId);
        if ($actor === null) {
            return;
        }

        if ($this->kind === 'comment') {
            $comment = Comment::query()->with('item')->find($this->id);
            if ($comment !== null && $comment->item?->isApproved()) {
                $this->send($notifications, $db, new NewCommentBlueprint($comment, $actor), $comment->item);
            }

            return;
        }

        $review = Review::query()->with(['item', 'merchant'])->find($this->id);
        if ($review === null || ! $review->isApproved() || ! $review->item?->isApproved()) {
            return;
        }

        if ($this->kind === 'review') {
            $this->send($notifications, $db, new NewReviewBlueprint($review, $actor), $review->item);
        }

        // Tagging is told once, to a merchant who wasn't on the review before (an edit that keeps them says nothing).
        $merchant = $review->merchant;
        if ($merchant !== null && $merchant->id !== $actor->id && $merchant->id !== $this->previousMerchantId
            && Review::whereVisibleTo($merchant)->whereKey($review->id)->exists()) {
            $notifications->sync(new MerchantTaggedBlueprint($review, $actor), [$merchant]);
        }
    }

    /**
     * The item's earlier reviewers and commenters who may view reviews. The subject is approved on an approved item,
     * so seeing it comes down to the `communityReviews.view` permission: checked set-wise, never per person.
     */
    private function send(NotificationSyncer $notifications, ConnectionInterface $db, NewReviewBlueprint|NewCommentBlueprint $blueprint, Item $item): void
    {
        $ids = $db->table('community_reviews')->where('item_id', $item->id)->where('status', 'approved')->whereNotNull('user_id')->pluck('user_id')
            ->merge($db->table('community_review_comments')->where('item_id', $item->id)->whereNotNull('user_id')->pluck('user_id'))
            ->map(fn ($id) => (int) $id)->unique()->reject(fn (int $id) => $id === $this->actorId)->take(self::CAP)->values()->all();
        if ($ids === []) {
            return;
        }

        $query = User::query()->whereIn('users.id', $ids)->where('users.is_email_confirmed', true);
        $viewers = $db->table('group_permission')->whereIn('permission', ['communityReviews.view', 'communityReviews.moderate'])->pluck('group_id')
            ->map(fn ($id) => (int) $id)->all();
        if (array_intersect($viewers, [Group::GUEST_ID, Group::MEMBER_ID]) === []) {
            $query->whereIn('users.id', fn (QueryBuilder $q) => $q->select('user_id')->from('group_user')
                ->whereIn('group_id', array_merge([Group::ADMINISTRATOR_ID], $viewers)));
        }

        $recipients = $query->get()->all();
        if ($recipients !== []) {
            $notifications->sync($blueprint, $recipients);
        }
    }
}
