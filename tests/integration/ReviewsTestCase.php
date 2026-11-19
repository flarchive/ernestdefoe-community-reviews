<?php

declare(strict_types=1);

namespace ErnestDefoe\CommunityReviews\Tests\integration;

use Carbon\Carbon;
use Flarum\Group\Group;
use Flarum\Testing\integration\TestCase;
use Flarum\User\User;
use Laminas\Diactoros\Stream;
use Laminas\Diactoros\UploadedFile;
use Psr\Http\Message\ResponseInterface;

/**
 * Members used across the suite: 1 admin, 2 reviewer, 3 other member, 4 moderator, 5 merchant (group 10).
 * Category 1 "Razors" with criteria 1 Smoothness, 2 Efficiency; category 2 "Soaps" with criterion 3 Lather.
 */
abstract class ReviewsTestCase extends TestCase
{
    /** @var list<string> */
    protected array $alsoEnable = [];

    protected function setUp(): void
    {
        parent::setUp();
        $this->extension(...[...$this->alsoEnable, 'ernestdefoe-community-reviews']);

        $users = [];
        foreach ([2 => 'reviewer', 3 => 'other', 4 => 'moderator', 5 => 'merchant'] as $id => $name) {
            $users[] = ['id' => $id, 'username' => $name, 'email' => "$name@machine.local", 'is_email_confirmed' => 1, 'password' => 'x', 'joined_at' => Carbon::now()->subYear()];
        }
        $now = Carbon::now();
        $this->prepareDatabase([
            User::class => $users,
            Group::class => [['id' => 10, 'name_singular' => 'Merchant', 'name_plural' => 'Merchants']],
            'group_user' => [['user_id' => 4, 'group_id' => Group::MODERATOR_ID], ['user_id' => 5, 'group_id' => 10]],
            'community_review_categories' => [
                ['id' => 1, 'name' => 'Razors', 'slug' => 'razors', 'position' => 0, 'created_at' => $now],
                ['id' => 2, 'name' => 'Soaps', 'slug' => 'soaps', 'position' => 1, 'created_at' => $now],
            ],
            'community_review_criteria' => [
                ['id' => 1, 'category_id' => 1, 'name' => 'Smoothness', 'weight' => 1, 'position' => 0],
                ['id' => 2, 'category_id' => 1, 'name' => 'Efficiency', 'weight' => 1, 'position' => 1],
                ['id' => 3, 'category_id' => 2, 'name' => 'Lather', 'weight' => 1, 'position' => 0],
            ],
        ]);
        $this->setting('ernestdefoe-community-reviews.merchant_group', 10);
        // The flood guard has its own test; everywhere else it would only slow the suite.
        $this->setting('ernestdefoe-community-reviews.flood_seconds', 0);
    }

    /** @return array<string, mixed> */
    protected function json(ResponseInterface $response): array
    {
        $decoded = json_decode((string) $response->getBody(), true);

        return is_array($decoded) ? $decoded : [];
    }

    /** @return array<string, mixed> a valid review for category 1 */
    protected function review(array $overrides = []): array
    {
        return $overrides + [
            'scores' => [
                ['criterionId' => 1, 'score' => 5, 'comment' => 'Very smooth.'],
                ['criterionId' => 2, 'score' => 4, 'comment' => 'Two passes.'],
            ],
            'title' => 'Great razor',
            'body' => 'Two years in, still my daily.',
            'price' => '$54',
            'url' => 'example.com/razor',
        ];
    }

    /** @param array<string, mixed> $attributes */
    protected function addItem(int $as, array $attributes = []): ResponseInterface
    {
        return $this->send($this->request('POST', '/api/review-items', [...($as > 0 ? ['authenticatedAs' => $as] : []), 'json' => ['data' => [
            'type' => 'review-items',
            'attributes' => $attributes + ['categoryId' => 1, 'name' => 'Kestrel Twin Bar', 'review' => $this->review()],
        ]]]));
    }

    protected function itemId(int $as = 2, array $attributes = []): int
    {
        $response = $this->addItem($as, $attributes);
        $this->assertSame(201, $response->getStatusCode(), substr((string) $response->getBody(), 0, 1500));

        return (int) $this->json($response)['data']['id'];
    }

    /** @param array<string, mixed> $attributes */
    protected function postReview(int $as, int $itemId, array $attributes): ResponseInterface
    {
        return $this->send($this->request('POST', '/api/reviews', ['authenticatedAs' => $as, 'json' => ['data' => [
            'type' => 'reviews',
            'attributes' => $attributes,
            'relationships' => ['item' => ['data' => ['type' => 'review-items', 'id' => (string) $itemId]]],
        ]]]));
    }

    protected function upload(int $as, string $bytes): ResponseInterface
    {
        $stream = new Stream('php://temp', 'wb+');
        $stream->write($bytes);
        $stream->rewind();

        return $this->send(
            $this->request('POST', '/api/review-photos', ['authenticatedAs' => $as])
                ->withUploadedFiles(['file' => new UploadedFile($stream, strlen($bytes), UPLOAD_ERR_OK, 'photo.jpg', 'image/jpeg')])
        );
    }

    protected function jpeg(int $w = 60, int $h = 40): string
    {
        $image = imagecreatetruecolor($w, $h);
        imagefill($image, 0, 0, (int) imagecolorallocate($image, 200, 30, 90));
        ob_start();
        imagejpeg($image);

        return (string) ob_get_clean();
    }

    protected function get(string $path, ?int $as = null, array $query = []): ResponseInterface
    {
        return $this->send($this->request('GET', $path, $as ? ['authenticatedAs' => $as] : [])->withQueryParams($query));
    }
}
