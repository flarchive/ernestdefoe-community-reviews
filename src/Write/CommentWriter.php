<?php

declare(strict_types=1);

namespace ErnestDefoe\CommunityReviews\Write;

use Carbon\Carbon;
use ErnestDefoe\CommunityReviews\Config;
use ErnestDefoe\CommunityReviews\Event\CommentSaved;
use ErnestDefoe\CommunityReviews\Job\RefreshItem;
use ErnestDefoe\CommunityReviews\Model\Comment;
use ErnestDefoe\CommunityReviews\Model\Item;
use ErnestDefoe\CommunityReviews\Model\Review;
use Flarum\User\User;
use Illuminate\Contracts\Events\Dispatcher;
use Illuminate\Contracts\Queue\Queue;
use Illuminate\Database\ConnectionInterface;

/** MyBB product comments: on an item, optionally in reply to one of its reviews, within the admin's length limit. */
class CommentWriter
{
    public function __construct(
        private readonly Config $config,
        private readonly Fail $fail,
        private readonly ConnectionInterface $db,
        private readonly Queue $queue,
        private readonly Dispatcher $events,
    ) {
    }

    /** @param array<string, mixed> $input */
    public function prepare(Comment $comment, ?Item $item, User $actor, array $input, ?string $ip): void
    {
        $isNew = ! $comment->exists;
        if ($isNew) {
            if ($item === null) {
                $this->fail->on('item', 'item_missing');
            }
            $actor->assertCan('comment', $item);
            $comment->item_id = $item->id;
            $comment->user_id = $actor->id;
            $comment->ip_address = $ip;
            $comment->review_id = $this->replyTo($item, $actor, $input['reviewId'] ?? null);
        } else {
            $actor->assertCan('edit', $comment);
            $comment->edited_at = Carbon::now();
        }

        if ($isNew || array_key_exists('body', $input)) {
            $body = Clean::text($input['body'] ?? null);
            $max = max(1, $this->config->int('comment_max'));
            if ($body === null) {
                $this->fail->on('body', 'comment_empty');
            }
            if (mb_strlen($body) > $max) {
                $this->fail->on('body', 'comment_too_long_plain', ['max' => $max]);
            }
            $comment->setBodySource($body, $actor);
        }

        if ($isNew && ! empty($input['isOfficial']) && $actor->hasPermission('communityReviews.moderate')) {
            $comment->is_official = true;
        }
    }

    public function saved(Comment $comment, User $actor, bool $isNew): void
    {
        $this->db->afterCommit(function () use ($comment, $actor, $isNew): void {
            $this->queue->push(new RefreshItem($comment->item_id));
            if ($comment->review_id !== null) {
                $this->recountReplies($comment->review_id);
            }
            if ($isNew) {
                $this->events->dispatch(new CommentSaved($comment, $actor));
            }
        });
    }

    public function deleted(Comment $comment): void
    {
        $this->db->afterCommit(function () use ($comment): void {
            $this->queue->push(new RefreshItem($comment->item_id));
            if ($comment->review_id !== null) {
                $this->recountReplies($comment->review_id);
            }
        });
    }

    /** A reply must point at a review of the same item that the member can see (MyBB drops it otherwise). */
    private function replyTo(Item $item, User $actor, mixed $reviewId): ?int
    {
        if (! is_numeric($reviewId)) {
            return null;
        }
        $review = Review::whereVisibleTo($actor)->whereKey((int) $reviewId)->where('item_id', $item->id)->first();

        return $review?->id;
    }

    private function recountReplies(int $reviewId): void
    {
        Review::query()->whereKey($reviewId)->update([
            'comment_count' => Comment::query()->where('review_id', $reviewId)->count(),
        ]);
    }
}
