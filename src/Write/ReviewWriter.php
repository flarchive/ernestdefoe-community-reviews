<?php

declare(strict_types=1);

namespace ErnestDefoe\CommunityReviews\Write;

use Carbon\Carbon;
use ErnestDefoe\CommunityReviews\Config;
use ErnestDefoe\CommunityReviews\Event\ReviewSaved;
use ErnestDefoe\CommunityReviews\Job\RefreshItem;
use ErnestDefoe\CommunityReviews\Merchants;
use ErnestDefoe\CommunityReviews\Model\Criterion;
use ErnestDefoe\CommunityReviews\Model\Item;
use ErnestDefoe\CommunityReviews\Model\Photo;
use ErnestDefoe\CommunityReviews\Model\Review;
use ErnestDefoe\CommunityReviews\Model\Score;
use Flarum\User\User;
use Illuminate\Contracts\Events\Dispatcher;
use Illuminate\Contracts\Queue\Queue;
use Illuminate\Database\ConnectionInterface;
use Illuminate\Support\Collection;

/**
 * Everything a review save checks and writes, MyBB's rules first:
 *
 * - every criterion of the item's category gets a 1-5 score and (unless the admin turned it off) a comment;
 * - the review's rating is the (weighted) average of those scores; there is no separate overall star;
 * - "General thoughts", price paid and a link are optional; the link must be a public http(s) address;
 * - the merchant must be a member of the Merchants group;
 * - photos: up to the admin's maximum, the member's own uploads, at least one when the admin requires it;
 * - several reviews per member per item, unless the one-per-member switch is on.
 */
class ReviewWriter
{
    /** @var array<int, array{scores: list<array{criterion: int, score: int, comment: ?string}>, photos: list<int>|null}> per review object id */
    private array $pending = [];

    public function __construct(
        private readonly Config $config,
        private readonly Fail $fail,
        private readonly Merchants $merchants,
        private readonly ConnectionInterface $db,
        private readonly Queue $queue,
        private readonly Dispatcher $events,
    ) {
    }

    /** @param array<string, mixed> $input the request's attributes */
    public function prepare(Review $review, Item $item, User $actor, array $input, ?string $ip): void
    {
        $isNew = ! $review->exists;
        if ($isNew) {
            $actor->assertCan('review', $item);
            $this->assertNotAgain($item, $actor);
            $review->item_id = $item->id;
            $review->user_id = $actor->id;
            $review->ip_address = $ip;
            $review->status = $this->config->bool('review_approval') && ! $actor->hasPermission('communityReviews.skipApproval')
                ? Review::PENDING : Review::APPROVED;
        } else {
            $actor->assertCan('edit', $review);
        }

        $scores = $this->scores($item, $input, $isNew);
        if ($scores !== null) {
            $rating = $this->rating($scores, $item);
            if (! $isNew && abs($rating - $review->rating) > 0.001) {
                $review->previous_rating = $review->rating;
            }
            $review->rating = $rating;
        }

        $this->texts($review, $input, $actor);
        $this->extras($review, $input, $actor);
        $photos = $this->photos($review, $input, $actor, $isNew);

        if (! $isNew) {
            $review->edited_at = Carbon::now();
        }
        $this->pending[spl_object_id($review)] = ['scores' => $scores ?? [], 'photos' => $photos];
    }

    /** After the review row is saved: its scores and photos, in the same transaction. */
    public function saveChildren(Review $review): void
    {
        $data = $this->pending[spl_object_id($review)] ?? null;
        unset($this->pending[spl_object_id($review)]);
        if ($data === null) {
            return;
        }

        if ($data['scores'] !== []) {
            Score::query()->where('review_id', $review->id)->delete();
            Score::query()->insert(array_map(fn (array $s): array => [
                'review_id' => $review->id,
                'criterion_id' => $s['criterion'],
                'score' => $s['score'],
                'comment' => $s['comment'],
            ], $data['scores']));
        }

        if ($data['photos'] !== null) {
            Photo::query()->where('review_id', $review->id)->whereNotIn('id', $data['photos'])->update(['review_id' => null]);
            foreach ($data['photos'] as $position => $id) {
                Photo::query()->whereKey($id)->update(['review_id' => $review->id, 'position' => $position]);
            }
        }
    }

    /** Once committed: recount the item, then tell listeners (notifications, Renown). */
    public function saved(Review $review, User $actor, bool $isNew, ?int $previousMerchant): void
    {
        $this->db->afterCommit(function () use ($review, $actor, $isNew, $previousMerchant): void {
            $this->queue->push(new RefreshItem($review->item_id));
            $this->events->dispatch(new ReviewSaved($review, $actor, $isNew, $previousMerchant));
        });
    }

    public function deleted(Review $review): void
    {
        $this->db->afterCommit(fn () => $this->queue->push(new RefreshItem($review->item_id)));
    }

    private function assertNotAgain(Item $item, User $actor): void
    {
        if ($this->config->bool('one_per_member')
            && Review::query()->where('item_id', $item->id)->where('user_id', $actor->id)->exists()) {
            $this->fail->on('item', 'already_reviewed');
        }
    }

    /**
     * @param array<string, mixed> $input
     * @return list<array{criterion: int, score: int, comment: ?string}>|null null when an update leaves them alone
     */
    private function scores(Item $item, array $input, bool $isNew): ?array
    {
        if (! $isNew && ! array_key_exists('scores', $input)) {
            return null;
        }

        $criteria = $this->criteria($item);
        if ($criteria->isEmpty()) {
            $this->fail->on('scores', 'no_criteria');
        }

        $given = [];
        foreach (is_array($input['scores'] ?? null) ? $input['scores'] : [] as $row) {
            if (is_array($row) && isset($row['criterionId'])) {
                $given[(int) $row['criterionId']] = $row;
            }
        }

        $required = $this->config->bool('criterion_comment_required');
        $max = max(1, $this->config->int('comment_max'));
        $out = [];
        foreach ($criteria as $criterion) {
            $row = $given[$criterion->id] ?? [];
            $score = (int) ($row['score'] ?? 0);
            $comment = Clean::text($row['comment'] ?? null);
            if ($score < 1 || $score > 5) {
                $this->fail->on('scores', 'score_missing', ['criterion' => $criterion->name]);
            }
            if ($comment === null && $required) {
                $this->fail->on('scores', 'comment_missing', ['criterion' => $criterion->name]);
            }
            if ($comment !== null && mb_strlen($comment) > $max) {
                $this->fail->on('scores', 'comment_too_long', ['criterion' => $criterion->name, 'max' => $max]);
            }
            $out[] = ['criterion' => $criterion->id, 'score' => $score, 'comment' => $comment];
        }

        return $out;
    }

    /** @return Collection<int, Criterion> */
    private function criteria(Item $item): Collection
    {
        return Criterion::query()->where('category_id', $item->category_id)->orderBy('position')->orderBy('id')->get();
    }

    /** @param list<array{criterion: int, score: int, comment: ?string}> $scores */
    private function rating(array $scores, Item $item): float
    {
        $weights = $this->criteria($item)->pluck('weight', 'id');
        $points = 0.0;
        $total = 0;
        foreach ($scores as $s) {
            $w = max(1, (int) ($weights[$s['criterion']] ?? 1));
            $points += $s['score'] * $w;
            $total += $w;
        }

        return $total > 0 ? round($points / $total, 2) : 0.0;
    }

    /** @param array<string, mixed> $input */
    private function texts(Review $review, array $input, User $actor): void
    {
        if (array_key_exists('title', $input)) {
            $title = Clean::text($input['title']);
            if ($title !== null && mb_strlen($title) > 150) {
                $this->fail->on('title', 'title_too_long', ['max' => 150]);
            }
            $review->title = $title;
        }

        if (array_key_exists('body', $input)) {
            $body = Clean::text($input['body']);
            $max = max(1, $this->config->int('general_max'));
            if ($body !== null && mb_strlen($body) > $max) {
                $this->fail->on('body', 'body_too_long', ['max' => $max]);
            }
            $review->setBodySource($body, $actor);
        }

        if (! $this->config->bool('ask_price_link')) {
            return;
        }
        if (array_key_exists('price', $input)) {
            $review->price = Clean::price($input['price']);
        }
        if (array_key_exists('url', $input)) {
            $url = Clean::text($input['url']);
            $link = $url === null ? null : LinkCheck::normalise($url);
            if ($url !== null && $link === null) {
                $this->fail->on('url', 'url_invalid');
            }
            $review->url = $link;
        }
    }

    /** @param array<string, mixed> $input */
    private function extras(Review $review, array $input, User $actor): void
    {
        // "merchantName", not "merchant": that name belongs to the review's merchant relationship.
        if (array_key_exists('merchantName', $input)) {
            $name = Clean::text($input['merchantName']);
            $merchant = $name === null ? null : $this->merchants->find($name);
            if ($name !== null && $merchant === null) {
                $this->fail->on('merchantName', 'merchant_invalid');
            }
            $review->merchant_id = $merchant?->id;
        }

        if ($this->config->extra('verified') && array_key_exists('ownedFor', $input)) {
            $owned = Clean::text($input['ownedFor']);
            $review->owned_for = in_array($owned, ['month', 'half', 'year', 'years', 'more'], true) ? $owned : null;
        }
        if ($this->config->extra('verified') && array_key_exists('isVerified', $input) && $actor->hasPermission('communityReviews.moderate')) {
            $review->is_verified = (bool) $input['isVerified'];
        }

        if ($this->config->extra('pros_cons')) {
            if (array_key_exists('pros', $input)) {
                $review->pros = Clean::labels($input['pros']);
            }
            if (array_key_exists('cons', $input)) {
                $review->cons = Clean::labels($input['cons']);
            }
        }

        if (array_key_exists('status', $input) && $review->exists && $actor->hasPermission('communityReviews.moderate')
            && in_array($input['status'], [Review::APPROVED, Review::PENDING], true)) {
            $review->status = $input['status'];
        }
    }

    /**
     * @param array<string, mixed> $input
     * @return list<int>|null the photo ids in order, or null when an update leaves them alone
     */
    private function photos(Review $review, array $input, User $actor, bool $isNew): ?array
    {
        if (! $isNew && ! array_key_exists('photos', $input)) {
            return null;
        }

        $ids = Clean::ids($input['photos'] ?? []);
        $max = $this->config->maxPhotos();
        if (count($ids) > $max) {
            $this->fail->on('photos', 'too_many_photos', ['max' => $max]);
        }
        if ($ids === [] && $this->config->bool('require_photo')) {
            $this->fail->on('photos', 'photo_required');
        }

        if ($ids !== []) {
            $owner = $review->user_id ?? $actor->id;
            $usable = Photo::query()->whereIn('id', $ids)->where('user_id', $owner)
                ->where(fn ($q) => $q->whereNull('review_id')->when($review->exists, fn ($q) => $q->orWhere('review_id', $review->id)))
                ->count();
            if ($usable !== count($ids)) {
                $this->fail->on('photos', 'photo_invalid');
            }
        }

        return $ids;
    }
}
