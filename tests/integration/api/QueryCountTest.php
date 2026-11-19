<?php

declare(strict_types=1);

namespace ErnestDefoe\CommunityReviews\Tests\integration\api;

use Carbon\Carbon;
use ErnestDefoe\CommunityReviews\Tests\integration\ReviewsTestCase;
use PHPUnit\Framework\Attributes\Test;

/**
 * 🚨 The catalog, an item's reviews and its comments cost the same number of queries for 1 row or 20,
 * for a guest, a member and a moderator.
 */
class QueryCountTest extends ReviewsTestCase
{
    private function seed(int $n, int $from): void
    {
        $now = Carbon::now();
        for ($i = $from; $i < $from + $n; $i++) {
            $this->database()->table('users')->insert(['id' => $i, 'username' => "u$i", 'email' => "u$i@machine.local", 'is_email_confirmed' => 1, 'password' => 'x']);
            $item = $this->database()->table('community_review_items')->insertGetId(['category_id' => 1, 'user_id' => $i, 'name' => "Item $i", 'slug' => "item-$i", 'status' => 'approved', 'created_at' => $now, 'rating' => 4]);
            $review = $this->database()->table('community_reviews')->insertGetId(['item_id' => 1, 'user_id' => $i, 'rating' => 4, 'merchant_id' => 5, 'status' => 'approved', 'created_at' => $now]);
            $this->database()->table('community_review_scores')->insert([['review_id' => $review, 'criterion_id' => 1, 'score' => 4, 'comment' => 'x'], ['review_id' => $review, 'criterion_id' => 2, 'score' => 4, 'comment' => 'y']]);
            $photo = $this->database()->table('community_review_photos')->insertGetId(['review_id' => $review, 'user_id' => $i, 'path' => null, 'url' => 'https://i.imgur.com/abcde.jpg', 'created_at' => $now]);
            $this->database()->table('community_review_items')->where('id', $item)->update(['thumbnail_photo_id' => $photo]);
            $this->database()->table('community_review_comments')->insert(['item_id' => 1, 'review_id' => $review, 'user_id' => $i, 'body' => '<t>hi</t>', 'created_at' => $now]);
        }
    }

    private function queries(string $path, array $query, ?int $as): int
    {
        $this->get($path, $as, $query); // warm settings and permissions
        $count = 0;
        $this->database()->listen(function () use (&$count): void {
            $count++;
        });
        $response = $this->get($path, $as, $query);
        $this->assertSame(200, $response->getStatusCode(), substr((string) $response->getBody(), 0, 1000));

        return $count;
    }

    #[Test]
    public function lists_cost_the_same_for_one_row_or_twenty(): void
    {
        $this->database()->table('community_review_items')->insert(['id' => 1, 'category_id' => 1, 'name' => 'Host', 'slug' => 'host', 'status' => 'approved', 'created_at' => Carbon::now()]);
        $lists = [
            ['/api/review-items', ['page' => ['limit' => 50]]],
            ['/api/reviews', ['filter' => ['item' => 1], 'page' => ['limit' => 50], 'include' => 'user,merchant,item']],
            ['/api/review-comments', ['filter' => ['item' => 1], 'page' => ['limit' => 50]]],
        ];

        $this->seed(1, 100);
        $before = [];
        foreach ([null, 3, 4] as $as) {
            foreach ($lists as [$path, $query]) {
                $before[] = $this->queries($path, $query, $as);
            }
        }

        $this->seed(19, 200);
        $after = [];
        foreach ([null, 3, 4] as $as) {
            foreach ($lists as [$path, $query]) {
                $after[] = $this->queries($path, $query, $as);
            }
        }

        $this->assertSame($before, $after, 'A list costs more queries with more rows: an N+1 crept in.');
    }
}
