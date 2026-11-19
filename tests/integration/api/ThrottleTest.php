<?php

declare(strict_types=1);

namespace ErnestDefoe\CommunityReviews\Tests\integration\api;

use ErnestDefoe\CommunityReviews\Tests\integration\ReviewsTestCase;
use PHPUnit\Framework\Attributes\Test;

class ThrottleTest extends ReviewsTestCase
{
    #[Test]
    public function members_are_flood_guarded_and_moderators_are_not(): void
    {
        $this->setting('ernestdefoe-community-reviews.flood_seconds', 10);
        $item = $this->itemId(2);

        $this->assertSame(429, $this->postReview(2, $item, $this->review())->getStatusCode());
        $this->assertSame(201, $this->postReview(3, $item, $this->review())->getStatusCode());
        $this->assertSame(201, $this->postReview(4, $item, $this->review())->getStatusCode());
        $this->assertSame(201, $this->postReview(4, $item, $this->review())->getStatusCode());
    }
}
