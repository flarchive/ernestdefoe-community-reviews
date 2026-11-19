<?php

declare(strict_types=1);

namespace ErnestDefoe\CommunityReviews\Photo;

use Carbon\Carbon;
use ErnestDefoe\CommunityReviews\Model\Photo;
use Illuminate\Database\Eloquent\Builder;

/**
 * Photo files never outlive their rows: before a review, an item or a member goes (the database then removes the
 * rows by cascade), their files are deleted here, a chunk at a time. Uploads no review used are pruned after a day
 * (Console\PruneCommand).
 */
class Cleanup
{
    public function __construct(private readonly Storage $storage)
    {
    }

    /** @param Builder<Photo> $photos */
    public function files(Builder $photos): void
    {
        $photos->whereNotNull('path')->select(['id', 'path', 'thumb_path'])->chunkById(200, function ($chunk): void {
            $this->storage->delete($chunk->flatMap(fn (Photo $p) => [$p->path, $p->thumb_path]));
        });
    }

    public function forReviews(Builder $reviews): void
    {
        $this->files(Photo::query()->whereIn('review_id', $reviews->select('community_reviews.id')));
    }

    public function forUser(int $userId): void
    {
        $this->files(Photo::query()->where('user_id', $userId));
    }

    /** @return int how many unused uploads were removed */
    public function pruneUnused(): int
    {
        $old = Photo::query()->whereNull('review_id')->where('created_at', '<', Carbon::now()->subDay());
        $count = (clone $old)->count();
        $this->files(clone $old);
        $old->delete();

        return $count;
    }
}
