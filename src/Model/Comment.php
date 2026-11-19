<?php

declare(strict_types=1);

namespace ErnestDefoe\CommunityReviews\Model;

use Carbon\CarbonInterface;
use Flarum\Database\AbstractModel;
use Flarum\Database\ScopeVisibilityTrait;
use Flarum\Formatter\Formatter;
use Flarum\User\User;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Psr\Http\Message\ServerRequestInterface;

/**
 * A comment on an item (MyBB "product comment"), optionally in reply to one of its reviews.
 * Seen by whoever can see the item (Access\CommentVisibility).
 *
 * @property int $id
 * @property int $item_id
 * @property int|null $review_id
 * @property int|null $user_id
 * @property string $body
 * @property bool $is_official
 * @property string|null $ip_address
 * @property CarbonInterface|null $edited_at
 * @property CarbonInterface|null $created_at
 * @property CarbonInterface|null $updated_at
 * @property-read Item|null $item
 * @property-read Review|null $review
 * @property-read User|null $user
 */
class Comment extends AbstractModel
{
    use ScopeVisibilityTrait;

    protected static ?Formatter $formatter = null;

    protected $table = 'community_review_comments';

    public $timestamps = true;

    protected $casts = [
        'item_id' => 'integer',
        'review_id' => 'integer',
        'user_id' => 'integer',
        'is_official' => 'boolean',
        'edited_at' => 'datetime',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    public static function setFormatter(Formatter $formatter): void
    {
        static::$formatter = $formatter;
    }

    public function setBodySource(string $source, ?User $actor = null): void
    {
        $this->attributes['body'] = static::$formatter === null ? $source : static::$formatter->parse(trim($source), $this, $actor ?? $this->user);
    }

    public function bodySource(): string
    {
        $body = (string) ($this->attributes['body'] ?? '');

        return static::$formatter === null ? $body : static::$formatter->unparse($body, $this);
    }

    public function bodyHtml(?ServerRequestInterface $request = null): string
    {
        $body = (string) ($this->attributes['body'] ?? '');

        return static::$formatter === null ? e($body) : static::$formatter->render($body, $this, $request);
    }

    /** @return BelongsTo<Item, $this> */
    public function item(): BelongsTo
    {
        return $this->belongsTo(Item::class, 'item_id');
    }

    /** @return BelongsTo<Review, $this> */
    public function review(): BelongsTo
    {
        return $this->belongsTo(Review::class, 'review_id');
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
