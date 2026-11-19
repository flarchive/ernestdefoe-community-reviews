<?php

declare(strict_types=1);

namespace ErnestDefoe\CommunityReviews\Write;

use ErnestDefoe\CommunityReviews\Job\RefreshItem;
use ErnestDefoe\CommunityReviews\Model\Comment;
use ErnestDefoe\CommunityReviews\Model\Item;
use ErnestDefoe\CommunityReviews\Model\Report;
use ErnestDefoe\CommunityReviews\Model\Review;
use Illuminate\Contracts\Queue\Queue;
use Illuminate\Database\ConnectionInterface;

/**
 * MyBB "Merge products": the source's reviews and comments move to the target, the view counts add up, and
 * the source is deleted. Three set-based updates, whatever the size.
 */
class Merger
{
    public function __construct(
        private readonly ConnectionInterface $db,
        private readonly Queue $queue,
        private readonly Fail $fail,
    ) {
    }

    public function merge(Item $source, Item $target): Item
    {
        if ($source->id === $target->id) {
            $this->fail->on('target', 'merge_same');
        }

        $this->db->transaction(function () use ($source, $target): void {
            Review::query()->where('item_id', $source->id)->update(['item_id' => $target->id]);
            Comment::query()->where('item_id', $source->id)->update(['item_id' => $target->id]);
            Item::query()->whereKey($target->id)->increment('view_count', $source->view_count);
            Report::query()->where('kind', 'item')->where('target_id', $source->id)->delete();
            $source->delete();
        });

        $this->queue->push(new RefreshItem($target->id));
        if ($source->category_id !== $target->category_id) {
            $this->queue->push(new \ErnestDefoe\CommunityReviews\Job\RefreshCategory($source->category_id));
        }

        return $target->refresh();
    }
}
