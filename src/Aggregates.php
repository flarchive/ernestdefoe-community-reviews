<?php

declare(strict_types=1);

namespace ErnestDefoe\CommunityReviews;

use Carbon\Carbon;
use ErnestDefoe\CommunityReviews\Model\Category;
use ErnestDefoe\CommunityReviews\Model\Item;
use Illuminate\Database\ConnectionInterface;
use Illuminate\Database\Query\Builder;

/**
 * Keeps an item's counts and averages on its row (MyBB's cached_rating, plus counts, thumbnail and ranking),
 * so no list ever computes them. Runs in the queue (Job\RefreshItem), a fixed handful of aggregate queries per
 * item whatever its size.
 *
 * - rating: weighted average of every criterion score of every approved review (weights default to 1 = MyBB's
 *   plain average);
 * - rating_rank: a Bayesian average, set only once the item has `rank_min_reviews` reviews, for "Top rated";
 * - top_mentions: the most mentioned pros and cons (only while the pros/cons extra is on).
 */
class Aggregates
{
    /** Reviews read for the pros/cons tally: the newest ones, enough to be representative, never unbounded. */
    private const MENTION_SAMPLE = 500;

    public function __construct(
        private readonly ConnectionInterface $db,
        private readonly Config $config,
    ) {
    }

    public function refreshItem(int $itemId): void
    {
        $item = Item::query()->find($itemId);
        if ($item === null) {
            return;
        }

        $approved = fn (): Builder => $this->db->table('community_reviews')->where('item_id', $itemId)->where('status', 'approved');
        $reviewCount = (int) $approved()->count();
        [$rating, $criteria] = $this->ratings($itemId);

        $item->review_count = $reviewCount;
        $item->comment_count = (int) $this->db->table('community_review_comments')->where('item_id', $itemId)->count();
        $item->rating = $rating;
        $item->criteria_avg = $criteria;
        $item->distribution = $this->distribution($itemId);
        $item->thumbnail_photo_id = $this->thumbnail($itemId);
        $item->last_activity_at = $this->lastActivity($itemId, $item);
        $item->top_mentions = $this->config->extra('pros_cons') ? $this->mentions($itemId) : null;
        $item->rating_rank = $this->rank($item, $reviewCount, $rating);
        $item->timestamps = false;
        $item->save();

        $this->refreshCategory($item->category_id);
    }

    public function refreshCategory(int $categoryId): void
    {
        $items = $this->db->table('community_review_items')->where('category_id', $categoryId)->where('status', 'approved');
        Category::query()->whereKey($categoryId)->update([
            'item_count' => (int) (clone $items)->count(),
            'review_count' => (int) (clone $items)->sum('review_count'),
        ]);
    }

    /** @return array{0: float|null, 1: array<string, float>|null} the overall rating and the average per criterion id */
    private function ratings(int $itemId): array
    {
        $scores = fn (): Builder => $this->db->table('community_review_scores')
            ->join('community_reviews', 'community_reviews.id', '=', 'community_review_scores.review_id')
            ->join('community_review_criteria', 'community_review_criteria.id', '=', 'community_review_scores.criterion_id')
            ->where('community_reviews.item_id', $itemId)
            ->where('community_reviews.status', 'approved');

        $grammar = $this->db->getQueryGrammar();
        $score = $grammar->wrap('community_review_scores.score');
        $weight = $grammar->wrap('community_review_criteria.weight');
        $total = $scores()->selectRaw("SUM($score * $weight) AS points, SUM($weight) AS weights")->first();
        $weights = (float) ($total->weights ?? 0);
        $rating = $weights > 0 ? round((float) $total->points / $weights, 2) : null;

        $criteria = [];
        foreach ($scores()->groupBy('community_review_scores.criterion_id')
            ->selectRaw($grammar->wrap('community_review_scores.criterion_id')." AS criterion_id, AVG($score) AS average")
            ->get() as $row) {
            $criteria[(string) $row->criterion_id] = round((float) $row->average, 2);
        }

        return [$rating, $criteria === [] ? null : $criteria];
    }

    /** @return list<int> approved reviews per star, 1 to 5 (a review's rating rounded) */
    private function distribution(int $itemId): array
    {
        $counts = [0, 0, 0, 0, 0];
        $rows = $this->db->table('community_reviews')->where('item_id', $itemId)->where('status', 'approved')
            ->groupBy('rating')->selectRaw($this->db->getQueryGrammar()->wrap('rating').' AS rating, COUNT(*) AS n')->get();
        foreach ($rows as $row) {
            $star = max(1, min(5, (int) round((float) $row->rating)));
            $counts[$star - 1] += (int) $row->n;
        }

        return $counts;
    }

    /** MyBB shows a product with its reviews' first photos: the first photo of the oldest approved review that has one. */
    private function thumbnail(int $itemId): ?int
    {
        $id = $this->db->table('community_review_photos')
            ->join('community_reviews', 'community_reviews.id', '=', 'community_review_photos.review_id')
            ->where('community_reviews.item_id', $itemId)
            ->where('community_reviews.status', 'approved')
            ->orderBy('community_reviews.created_at')
            ->orderBy('community_reviews.id')
            ->orderBy('community_review_photos.position')
            ->orderBy('community_review_photos.id')
            ->value('community_review_photos.id');

        return $id === null ? null : (int) $id;
    }

    private function lastActivity(int $itemId, Item $item): ?Carbon
    {
        $review = $this->db->table('community_reviews')->where('item_id', $itemId)->where('status', 'approved')->max('created_at');
        $comment = $this->db->table('community_review_comments')->where('item_id', $itemId)->max('created_at');
        $times = array_filter([$review, $comment, $item->created_at?->toDateTimeString()]);

        return $times === [] ? null : Carbon::parse(max($times));
    }

    /** Ranked only with enough reviews; pulled towards the category's mean while it has few. */
    private function rank(Item $item, int $reviewCount, ?float $rating): ?float
    {
        $min = max(1, $this->config->int('rank_min_reviews'));
        if ($rating === null || $reviewCount < $min) {
            return null;
        }

        $mean = $this->db->table('community_review_items')
            ->where('category_id', $item->category_id)
            ->where('status', 'approved')
            ->whereNotNull('rating')
            ->avg('rating');
        $mean = $mean === null ? $rating : (float) $mean;

        return round(($min * $mean + $reviewCount * $rating) / ($min + $reviewCount), 3);
    }

    /** @return array{pros: array<string, int>, cons: array<string, int>}|null the five most mentioned of each */
    private function mentions(int $itemId): ?array
    {
        $tally = ['pros' => [], 'cons' => []];
        $rows = $this->db->table('community_reviews')->where('item_id', $itemId)->where('status', 'approved')
            ->orderByDesc('id')->limit(self::MENTION_SAMPLE)->get(['pros', 'cons']);
        foreach ($rows as $row) {
            foreach (['pros', 'cons'] as $side) {
                $list = json_decode((string) ($row->{$side} ?? ''), true);
                foreach (is_array($list) ? $list : [] as $label) {
                    $key = mb_strtolower(trim((string) $label));
                    if ($key !== '') {
                        $tally[$side][$key] = ($tally[$side][$key] ?? 0) + 1;
                    }
                }
            }
        }
        foreach ($tally as $side => $counts) {
            arsort($counts);
            $tally[$side] = array_slice($counts, 0, 5, true);
        }

        return $tally['pros'] === [] && $tally['cons'] === [] ? null : $tally;
    }
}
