<?php

declare(strict_types=1);

namespace ErnestDefoe\CommunityReviews\Tests\integration\api;

use ErnestDefoe\CommunityReviews\Tests\integration\ReviewsTestCase;
use PHPUnit\Framework\Attributes\Test;

class NotificationsAndHeadTest extends ReviewsTestCase
{
    private function notifications(string $type): array
    {
        return $this->database()->table('notifications')->where('type', $type)->pluck('user_id')->map(fn ($id) => (int) $id)->all();
    }

    #[Test]
    public function mybb_alerts_reach_the_merchant_and_earlier_posters(): void
    {
        $item = $this->itemId(2, ['review' => $this->review(['merchant' => 'merchant'])]);
        $this->assertSame([5], $this->notifications('communityReviewsMerchantTagged'));

        // Member 3 reviews: the earlier reviewer (2) hears about it, not the author.
        $this->assertSame(201, $this->postReview(3, $item, $this->review())->getStatusCode());
        $this->assertSame([2], $this->notifications('communityReviewsNewReview'));

        // A comment by 4: both earlier posters hear about it.
        $this->send($this->request('POST', '/api/review-comments', ['authenticatedAs' => 4, 'json' => ['data' => [
            'type' => 'review-comments', 'attributes' => ['body' => 'Nice'],
            'relationships' => ['item' => ['data' => ['type' => 'review-items', 'id' => (string) $item]]],
        ]]]));
        $heard = $this->notifications('communityReviewsNewComment');
        sort($heard);
        $this->assertSame([2, 3], $heard);

        // The merchant's alert lists through core's notifications endpoint (the subject has an API resource).
        $list = $this->json($this->get('/api/notifications', 5));
        $this->assertSame(200, $this->get('/api/notifications', 5)->getStatusCode());
        $this->assertNotEmpty($list['data']);
    }

    #[Test]
    public function rich_results_appear_only_when_switched_on_and_guests_can_see(): void
    {
        $this->setting('ernestdefoe-community-reviews.extra_rich_results', true);
        $item = $this->itemId(2);

        $page = (string) $this->get("/reviews/i/$item-kestrel-twin-bar")->getBody();
        $this->assertStringContainsString('application/ld+json', $page);
        $this->assertStringContainsString('"AggregateRating"', $page);
        $this->assertStringContainsString('<title>Kestrel Twin Bar', $page);
    }

    #[Test]
    public function no_rich_results_for_a_held_item_even_for_its_author(): void
    {
        $this->setting('ernestdefoe-community-reviews.extra_rich_results', true);
        $this->setting('ernestdefoe-community-reviews.item_approval', true);
        $item = $this->itemId(2);

        $this->assertStringNotContainsString('application/ld+json', (string) $this->get("/reviews/i/$item-x", 2)->getBody());
        $this->assertStringNotContainsString('Kestrel', (string) $this->get("/reviews/i/$item-x")->getBody());
    }

    #[Test]
    public function rich_results_are_off_by_default(): void
    {
        $item = $this->itemId(2);
        $this->assertStringNotContainsString('application/ld+json', (string) $this->get("/reviews/i/$item-kestrel-twin-bar")->getBody());
    }
}
