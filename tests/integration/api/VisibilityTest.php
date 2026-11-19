<?php

declare(strict_types=1);

namespace ErnestDefoe\CommunityReviews\Tests\integration\api;

use ErnestDefoe\CommunityReviews\Tests\integration\ReviewsTestCase;
use Flarum\Group\Group;
use PHPUnit\Framework\Attributes\Test;

/** One visibility rule per model: held items and reviews show only to their author and moderators. */
class VisibilityTest extends ReviewsTestCase
{
    #[Test]
    public function held_items_and_reviews_stay_with_their_author_and_moderators(): void
    {
        $this->setting('ernestdefoe-community-reviews.item_approval', true);
        $this->setting('ernestdefoe-community-reviews.review_approval', true);
        $id = $this->itemId(2);

        $this->assertSame(404, $this->get("/api/review-items/$id")->getStatusCode());
        $this->assertSame(404, $this->get("/api/review-items/$id", 3)->getStatusCode());
        $this->assertSame(200, $this->get("/api/review-items/$id", 2)->getStatusCode());
        $this->assertSame(200, $this->get("/api/review-items/$id", 4)->getStatusCode());
        $this->assertSame([], $this->json($this->get('/api/reviews', 3, ['filter' => ['item' => $id]]))['data']);
        $this->assertSame([], $this->json($this->get('/api/reviews'))['data']);
        $this->assertSame([], $this->json($this->get('/api/review-items'))['data']);

        // A held item's merchant summary and photos never count for others either.
        $pending = $this->json($this->get('/api/review-items', 4, ['filter' => ['status' => 'pending']]))['data'];
        $this->assertCount(1, $pending);
    }

    #[Test]
    public function without_the_view_permission_nothing_is_listed(): void
    {
        $id = $this->itemId(2);
        $this->database()->table('group_permission')->where('permission', 'communityReviews.view')->delete();
        $this->database()->table('group_permission')->insert(['group_id' => Group::MEMBER_ID, 'permission' => 'communityReviews.view']);

        $this->assertSame(404, $this->get("/api/review-items/$id")->getStatusCode());
        $this->assertSame([], $this->json($this->get('/api/reviews'))['data']);
        $this->assertSame([], $this->json($this->get('/api/review-categories'))['data']);
        $this->assertSame(200, $this->get("/api/review-items/$id", 3)->getStatusCode());
    }

    #[Test]
    public function the_merchant_average_counts_only_visible_reviews(): void
    {
        $id = $this->itemId(2, ['review' => $this->review(['merchant' => 'merchant'])]);
        $summary = $this->json($this->get('/api/reviews/merchant-summary', null, ['filter' => ['merchant' => 5]]))['data']['attributes'];
        $this->assertSame(1, $summary['reviewCount']);
        $this->assertEqualsWithDelta(4.5, $summary['rating'], 0.001);

        $this->database()->table('community_reviews')->update(['status' => 'pending']);
        $this->assertSame(0, $this->json($this->get('/api/reviews/merchant-summary', null, ['filter' => ['merchant' => 5]]))['data']['attributes']['reviewCount']);
        $this->assertSame(1, $this->json($this->get('/api/reviews', 5, ['filter' => ['merchant' => 5]]))['data'] === [] ? 1 : 0);
        unset($id);
    }

    #[Test]
    public function only_merchants_group_members_can_be_tagged(): void
    {
        $this->assertSame(422, $this->addItem(2, ['review' => $this->review(['merchant' => 'other'])])->getStatusCode());
        $this->assertSame(201, $this->addItem(2, ['review' => $this->review(['merchant' => 'merchant'])])->getStatusCode());
    }

    #[Test]
    public function catalog_statistics_count_only_approved_items(): void
    {
        $id = $this->itemId(2);
        $stats = $this->json($this->get('/api/review-items/stats'))['data']['attributes'];
        $this->assertSame(['items' => 1, 'reviews' => 1, 'comments' => 0, 'views' => 0], $stats);
        // The stats route sits before Show's /{id}: the item itself still resolves (never assume an id: MySQL and
        // PostgreSQL sequences keep counting across tests).
        $this->assertSame(200, $this->get("/api/review-items/$id")->getStatusCode());
    }
}
