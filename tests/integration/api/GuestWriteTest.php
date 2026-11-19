<?php

declare(strict_types=1);

namespace ErnestDefoe\CommunityReviews\Tests\integration\api;

use ErnestDefoe\CommunityReviews\Tests\integration\ReviewsTestCase;
use Flarum\Api\Client;
use Flarum\User\Guest;
use PHPUnit\Framework\Attributes\Test;

/**
 * 🚨 Every write endpoint declares ->authenticated(): a guest is refused (401) and nothing is written. Sent through
 * Flarum's API client, so the endpoint's own check answers (over HTTP a session-less write stops at CSRF first).
 */
class GuestWriteTest extends ReviewsTestCase
{
    /** @param array<string, mixed> $body */
    private function asGuest(string $method, string $path, array $body = []): int
    {
        $client = $this->app()->getContainer()->make(Client::class)->withActor(new Guest())->withErrorHandling()->withBody($body);

        return $client->send($method, $path)->getStatusCode();
    }

    #[Test]
    public function guests_cannot_write_anything(): void
    {
        $id = $this->itemId();
        $review = (int) $this->database()->table('community_reviews')->value('id');
        $item = ['data' => ['type' => 'review-items', 'id' => (string) $id]];

        $creates = [
            '/review-items' => ['type' => 'review-items', 'attributes' => ['categoryId' => 1, 'name' => 'X', 'review' => $this->review()]],
            '/reviews' => ['type' => 'reviews', 'attributes' => $this->review(), 'relationships' => ['item' => $item]],
            '/review-comments' => ['type' => 'review-comments', 'attributes' => ['body' => 'hi'], 'relationships' => ['item' => $item]],
            '/review-reports' => ['type' => 'review-reports', 'attributes' => ['subjectType' => 'item', 'subjectId' => $id]],
            '/review-categories' => ['type' => 'review-categories', 'attributes' => ['name' => 'X']],
        ];
        foreach ($creates as $path => $data) {
            $this->assertSame(401, $this->asGuest('POST', $path, ['data' => $data]), $path);
        }
        $this->assertSame(401, $this->asGuest('POST', '/review-photos'));
        $this->assertSame(401, $this->asGuest('POST', "/review-items/$id/merge", ['targetId' => $id]));
        $this->assertSame(401, $this->asGuest('POST', "/reviews/$review/helpful"));
        $this->assertSame(401, $this->asGuest('DELETE', "/reviews/$review"));
        $this->assertSame(401, $this->asGuest('DELETE', "/review-items/$id"));

        $this->assertSame(1, $this->database()->table('community_review_items')->count());
        $this->assertSame(1, $this->database()->table('community_reviews')->count());
        $this->assertSame(0, $this->database()->table('community_review_comments')->count());
        $this->assertSame(0, $this->database()->table('community_review_reports')->count());
    }
}
