<?php

declare(strict_types=1);

namespace ErnestDefoe\CommunityReviews\Tests\integration\api;

use ErnestDefoe\CommunityReviews\Tests\integration\ReviewsTestCase;
use PHPUnit\Framework\Attributes\Test;

class ItemsTest extends ReviewsTestCase
{
    #[Test]
    public function a_member_adds_an_item_with_its_first_review_like_mybb(): void
    {
        $id = $this->itemId();

        $item = $this->json($this->get("/api/review-items/$id"))['data']['attributes'];
        $this->assertSame('Kestrel Twin Bar', $item['name']);
        $this->assertSame(1, $item['reviewCount']);
        $this->assertEqualsWithDelta(4.5, $item['rating'], 0.001);
        $this->assertSame(['1' => 5, '2' => 4], array_map('intval', $item['criteriaAverages']));

        $reviews = $this->json($this->get('/api/reviews', null, ['filter' => ['item' => $id]]))['data'];
        $this->assertCount(1, $reviews);
        $this->assertSame('$54', $reviews[0]['attributes']['price']);
        $this->assertSame('http://example.com/razor', $reviews[0]['attributes']['url']);
        $this->assertSame('Very smooth.', $reviews[0]['attributes']['scores'][0]['comment']);
    }

    #[Test]
    public function an_item_needs_its_first_review_and_a_unique_name(): void
    {
        $response = $this->send($this->request('POST', '/api/review-items', ['authenticatedAs' => 2, 'json' => ['data' => [
            'type' => 'review-items', 'attributes' => ['categoryId' => 1, 'name' => 'Lonely'],
        ]]]));
        $this->assertSame(422, $response->getStatusCode());
        $this->assertSame(0, $this->database()->table('community_review_items')->count());

        $this->itemId();
        $this->assertSame(422, $this->addItem(3, ['name' => 'KESTREL twin bar'])->getStatusCode());
    }

    #[Test]
    public function every_criterion_needs_a_score_and_a_comment(): void
    {
        $missing = $this->addItem(2, ['review' => $this->review(['scores' => [['criterionId' => 1, 'score' => 5, 'comment' => 'ok']]])]);
        $this->assertSame(422, $missing->getStatusCode());
        $this->assertStringContainsString('score_missing', (string) $missing->getBody());

        $noComment = $this->addItem(2, ['review' => $this->review(['scores' => [
            ['criterionId' => 1, 'score' => 5, 'comment' => 'ok'], ['criterionId' => 2, 'score' => 3],
        ]])]);
        $this->assertSame(422, $noComment->getStatusCode());
        $this->assertSame(0, $this->database()->table('community_review_items')->count());
    }

    #[Test]
    public function the_criterion_comment_can_be_made_optional(): void
    {
        $this->setting('ernestdefoe-community-reviews.criterion_comment_required', false);
        $response = $this->addItem(2, ['review' => $this->review(['scores' => [
            ['criterionId' => 1, 'score' => 5], ['criterionId' => 2, 'score' => 3],
        ]])]);
        $this->assertSame(201, $response->getStatusCode(), (string) $response->getBody());
    }

    #[Test]
    public function a_private_or_bad_url_is_refused(): void
    {
        foreach (['http://127.0.0.1/x', 'http://192.168.1.4', 'javascript:alert(1)', 'localhost:8080/a', 'not a url'] as $url) {
            $this->assertSame(422, $this->addItem(2, ['name' => 'X'.md5($url), 'review' => $this->review(['url' => $url])])->getStatusCode(), $url);
        }
    }

    #[Test]
    public function members_review_an_item_more_than_once_like_mybb(): void
    {
        $id = $this->itemId();
        $this->assertSame(201, $this->postReview(2, $id, $this->review())->getStatusCode());
    }

    #[Test]
    public function the_admin_can_allow_one_review_per_member(): void
    {
        // Settings are read once per booted app: set it before the first request.
        $this->setting('ernestdefoe-community-reviews.one_per_member', true);
        $id = $this->itemId();
        $this->assertSame(422, $this->postReview(2, $id, $this->review())->getStatusCode());
        $this->assertSame(201, $this->postReview(3, $id, $this->review())->getStatusCode());
    }

    #[Test]
    public function sorting_and_search_follow_mybb(): void
    {
        $a = $this->itemId(2, ['name' => 'Alpha razor', 'review' => $this->review(['scores' => [
            ['criterionId' => 1, 'score' => 2, 'comment' => 'x'], ['criterionId' => 2, 'score' => 2, 'comment' => 'x'],
        ]])]);
        $b = $this->itemId(2, ['name' => 'Beta razor']);

        $byName = $this->json($this->get('/api/review-items'))['data'];
        $this->assertSame([(string) $a, (string) $b], array_column($byName, 'id'));

        $byRating = $this->json($this->get('/api/review-items', null, ['sort' => '-rating']))['data'];
        $this->assertSame([(string) $b, (string) $a], array_column($byRating, 'id'));

        $found = $this->json($this->get('/api/review-items', null, ['filter' => ['q' => 'Beta']]))['data'];
        $this->assertSame([(string) $b], array_column($found, 'id'));
    }

    #[Test]
    public function merging_moves_reviews_and_comments_and_adds_views(): void
    {
        $source = $this->itemId(2, ['name' => 'Kestrel TB']);
        $target = $this->itemId(3, ['name' => 'Kestrel Twin Bar']);
        $this->database()->table('community_review_items')->where('id', $source)->update(['view_count' => 7]);

        $response = $this->send($this->request('POST', "/api/review-items/$source/merge", ['authenticatedAs' => 4, 'json' => ['targetId' => $target]]));
        $this->assertSame(200, $response->getStatusCode(), (string) $response->getBody());
        $this->assertNull($this->database()->table('community_review_items')->find($source));
        $row = $this->database()->table('community_review_items')->find($target);
        $this->assertSame(2, (int) $row->review_count);
        $this->assertSame(7, (int) $row->view_count);

        $this->assertSame(403, $this->send($this->request('POST', "/api/review-items/$target/merge", ['authenticatedAs' => 2, 'json' => ['targetId' => $target]]))->getStatusCode());
    }
}
