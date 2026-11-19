<?php

declare(strict_types=1);

namespace ErnestDefoe\CommunityReviews\Tests\integration\api;

use ErnestDefoe\CommunityReviews\Tests\integration\ReviewsTestCase;
use PHPUnit\Framework\Attributes\Test;

class CommentsPhotosReportsTest extends ReviewsTestCase
{
    private function comment(int $as, int $item, array $attributes): \Psr\Http\Message\ResponseInterface
    {
        return $this->send($this->request('POST', '/api/review-comments', ['authenticatedAs' => $as, 'json' => ['data' => [
            'type' => 'review-comments', 'attributes' => $attributes,
            'relationships' => ['item' => ['data' => ['type' => 'review-items', 'id' => (string) $item]]],
        ]]]));
    }

    #[Test]
    public function a_comment_can_reply_to_a_review_of_the_same_item(): void
    {
        $item = $this->itemId(2);
        $review = (int) $this->database()->table('community_reviews')->value('id');

        $response = $this->comment(3, $item, ['body' => 'Which blade?', 'reviewId' => $review]);
        $this->assertSame(201, $response->getStatusCode(), (string) $response->getBody());
        $this->assertSame('reviewer', $this->json($response)['data']['attributes']['replyToName']);
        $this->assertSame(1, (int) $this->database()->table('community_reviews')->where('id', $review)->value('comment_count'));
        $this->assertSame(1, (int) $this->database()->table('community_review_items')->where('id', $item)->value('comment_count'));

        // A reply pointing at another item's review is dropped (MyBB does the same).
        $other = $this->itemId(3, ['name' => 'Other razor']);
        $stray = $this->comment(3, $other, ['body' => 'hm', 'reviewId' => $review]);
        $this->assertNull($this->json($stray)['data']['attributes']['reviewId']);

        $list = $this->json($this->get('/api/review-comments', null, ['filter' => ['item' => $item]]))['data'];
        $this->assertCount(1, $list);
        $this->assertStringContainsString('Which blade?', $list[0]['attributes']['bodyHtml']);
        $this->assertArrayNotHasKey('body', $list[0]['attributes']);
    }

    #[Test]
    public function photos_are_re_encoded_and_follow_the_review(): void
    {
        $upload = $this->upload(2, $this->jpeg());
        $this->assertSame(201, $upload->getStatusCode(), (string) $upload->getBody());
        $photo = (int) $this->json($upload)['data']['id'];

        $id = $this->itemId(2, ['review' => $this->review(['photos' => [$photo]])]);
        $item = $this->json($this->get("/api/review-items/$id"))['data']['attributes'];
        $this->assertNotNull($item['thumbnailUrl']);

        $file = $this->get("/reviews/photo/$photo");
        $this->assertSame(200, $file->getStatusCode());
        $this->assertSame('image/webp', $file->getHeaderLine('Content-Type'));
        $this->assertStringStartsWith('RIFF', (string) $file->getBody());

        // Someone else can't attach it to their own review.
        $this->assertSame(422, $this->postReview(3, $id, $this->review(['photos' => [$photo]]))->getStatusCode());

        // Hidden review: its photo is gone for everyone but its author and moderators.
        $this->database()->table('community_reviews')->update(['status' => 'pending']);
        $this->assertSame(403, $this->get("/reviews/photo/$photo")->getStatusCode());
        $this->assertSame(200, $this->get("/reviews/photo/$photo", 2)->getStatusCode());
    }

    #[Test]
    public function a_photo_needs_real_image_bytes_and_respects_the_maximum(): void
    {
        $this->assertSame(422, $this->upload(2, '<?php echo 1;')->getStatusCode());

        $ids = [];
        for ($i = 0; $i < 5; $i++) {
            $ids[] = (int) $this->json($this->upload(2, $this->jpeg()))['data']['id'];
        }
        $this->assertSame(422, $this->addItem(2, ['review' => $this->review(['photos' => $ids])])->getStatusCode());
    }

    #[Test]
    public function reports_reach_moderators_once_per_member(): void
    {
        $item = $this->itemId(2);
        $review = (int) $this->database()->table('community_reviews')->value('id');
        $report = fn (int $as) => $this->send($this->request('POST', '/api/review-reports', ['authenticatedAs' => $as, 'json' => ['data' => [
            'type' => 'review-reports', 'attributes' => ['subjectType' => 'review', 'subjectId' => $review, 'reason' => 'spam'],
        ]]]));

        $this->assertSame(201, $report(3)->getStatusCode());
        $this->assertSame(422, $report(3)->getStatusCode());

        $this->assertSame(403, $this->get('/api/review-reports', 3)->getStatusCode());
        $open = $this->json($this->get('/api/review-reports', 4))['data'];
        $this->assertCount(1, $open);
        $this->assertSame((string) $item, $open[0]['relationships']['item']['data']['id']);
        $this->assertStringContainsString('Great razor', (string) $open[0]['attributes']['excerpt']);
    }

    #[Test]
    public function views_count_once_per_visitor_per_hour(): void
    {
        $item = $this->itemId(2);
        foreach ([3, 3, 4] as $as) {
            $this->assertSame(204, $this->send($this->request('POST', "/api/review-items/$item/view", ['authenticatedAs' => $as]))->getStatusCode());
        }
        $this->assertSame(2, (int) $this->database()->table('community_review_items')->where('id', $item)->value('view_count'));
    }
}
