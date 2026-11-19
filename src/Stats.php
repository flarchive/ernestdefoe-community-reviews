<?php

declare(strict_types=1);

namespace ErnestDefoe\CommunityReviews;

use Illuminate\Contracts\Cache\Repository as Cache;
use Illuminate\Database\ConnectionInterface;

/**
 * Catalog-wide totals over approved items only (so a held item never shows in a public number), summed from the
 * counts already on the item rows: one aggregate query, cached for ten minutes.
 */
class Stats
{
    private const KEY = 'community-reviews:stats';

    public function __construct(
        private readonly Cache $cache,
        private readonly ConnectionInterface $db,
    ) {
    }

    /** @return array{items: int, reviews: int, comments: int, views: int} */
    public function get(): array
    {
        return $this->cache->remember(self::KEY, 600, function (): array {
            $row = $this->db->table('community_review_items')->where('status', 'approved')
                ->selectRaw('COUNT(*) AS items, SUM(review_count) AS reviews, SUM(comment_count) AS comments, SUM(view_count) AS views')
                ->first();

            return [
                'items' => (int) ($row->items ?? 0),
                'reviews' => (int) ($row->reviews ?? 0),
                'comments' => (int) ($row->comments ?? 0),
                'views' => (int) ($row->views ?? 0),
            ];
        });
    }
}
