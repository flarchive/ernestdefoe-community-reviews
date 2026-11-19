<?php

declare(strict_types=1);

namespace ErnestDefoe\CommunityReviews\Gdpr;

use ErnestDefoe\CommunityReviews\Model\Comment;
use ErnestDefoe\CommunityReviews\Model\Photo;
use ErnestDefoe\CommunityReviews\Model\Review;
use ErnestDefoe\CommunityReviews\Aggregates;
use Flarum\Gdpr\Data\Type;

/**
 * A member's reviews (with their scores), comments and photo links in their data export. On anonymisation the
 * reviews and comments stay (the item's numbers don't move) without their name, address or photos; on deletion
 * they go.
 */
class ReviewsData extends Type
{
    public static function exportDescription(): string
    {
        return self::staticTranslator()->trans('ernestdefoe-community-reviews.lib.gdpr.export');
    }

    public static function anonymizeDescription(): string
    {
        return self::staticTranslator()->trans('ernestdefoe-community-reviews.lib.gdpr.anonymize');
    }

    public static function deleteDescription(): string
    {
        return self::staticTranslator()->trans('ernestdefoe-community-reviews.lib.gdpr.delete');
    }

    public function export(): ?array
    {
        $reviews = Review::query()->where('user_id', $this->user->id)->with(['scores.criterion', 'item'])->orderBy('id')->get()
            ->map(fn (Review $r) => [
                'item' => $r->item?->name,
                'rating' => $r->rating,
                'title' => $r->title,
                'general_thoughts' => $r->bodySource(),
                'price' => $r->price,
                'url' => $r->url,
                'scores' => $r->scores->map(fn ($s) => ['criterion' => $s->criterion?->name, 'score' => $s->score, 'comment' => $s->comment])->all(),
                'created_at' => $r->created_at?->toIso8601String(),
            ])->all();
        $comments = Comment::query()->where('user_id', $this->user->id)->orderBy('id')->get()
            ->map(fn (Comment $c) => ['item_id' => $c->item_id, 'comment' => $c->bodySource(), 'created_at' => $c->created_at?->toIso8601String()])->all();
        $photos = Photo::query()->where('user_id', $this->user->id)->orderBy('id')->get(['id', 'review_id', 'url', 'created_at'])->toArray();

        return [
            ['community-reviews/reviews.json' => $this->encodeForExport($reviews)],
            ['community-reviews/comments.json' => $this->encodeForExport($comments)],
            ['community-reviews/photos.json' => $this->encodeForExport($photos)],
        ];
    }

    /** The member's photo files, from the extension's own disk (gdpr hands every type its filesystem factory). */
    private function deletePhotos(): void
    {
        $disk = $this->getDisk('ernestdefoe-community-reviews');
        Photo::query()->where('user_id', $this->user->id)->whereNotNull('path')->select(['id', 'path', 'thumb_path'])
            ->chunkById(200, fn ($chunk) => $disk->delete($chunk->flatMap(fn (Photo $p) => [$p->path, $p->thumb_path])->filter()->values()->all()));
        Photo::query()->where('user_id', $this->user->id)->delete();
    }

    public function anonymize(): void
    {
        $this->deletePhotos();
        Review::query()->where('user_id', $this->user->id)->update(['user_id' => null, 'ip_address' => null]);
        Comment::query()->where('user_id', $this->user->id)->update(['user_id' => null, 'ip_address' => null]);
    }

    public function delete(): void
    {
        $this->deletePhotos();
        $items = Review::query()->where('user_id', $this->user->id)->distinct()->pluck('item_id')
            ->merge(Comment::query()->where('user_id', $this->user->id)->distinct()->pluck('item_id'))->unique();
        Review::query()->where('user_id', $this->user->id)->delete();
        Comment::query()->where('user_id', $this->user->id)->delete();
        // The items' numbers are recounted (erasure already runs in the queue, so straight away). resolve() because
        // flarum/gdpr builds each Type itself with a fixed constructor: there is no injection point.
        $aggregates = resolve(Aggregates::class);
        foreach ($items as $itemId) {
            $aggregates->refreshItem((int) $itemId);
        }
    }
}
