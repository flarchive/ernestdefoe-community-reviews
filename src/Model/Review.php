<?php

declare(strict_types=1);

namespace ErnestDefoe\CommunityReviews\Model;

use Carbon\CarbonInterface;
use Flarum\Database\AbstractModel;
use Flarum\Database\ScopeVisibilityTrait;
use Flarum\Formatter\Formatter;
use Flarum\User\User;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Psr\Http\Message\ServerRequestInterface;

/**
 * One member's review of an item: a score and comment per criterion (Score), "General thoughts" (body, stored as
 * formatter XML like a post), price paid, a link, the merchant it was bought from, and photos.
 * Who sees it: Access\ReviewVisibility.
 *
 * @property int $id
 * @property int $item_id
 * @property int|null $user_id
 * @property float $rating
 * @property string|null $title
 * @property string|null $body
 * @property string|null $price
 * @property string|null $url
 * @property int|null $merchant_id
 * @property string|null $owned_for
 * @property list<string>|null $pros
 * @property list<string>|null $cons
 * @property bool $is_verified
 * @property int $helpful_count
 * @property int $comment_count
 * @property string $status
 * @property float|null $previous_rating
 * @property string|null $ip_address
 * @property CarbonInterface|null $edited_at
 * @property CarbonInterface|null $created_at
 * @property CarbonInterface|null $updated_at
 * @property-read Item|null $item
 * @property-read User|null $user
 * @property-read User|null $merchant
 * @property-read \Illuminate\Database\Eloquent\Collection<int, Score> $scores
 * @property-read \Illuminate\Database\Eloquent\Collection<int, Photo> $photos
 */
class Review extends AbstractModel
{
    use ScopeVisibilityTrait;

    public const APPROVED = 'approved';

    public const PENDING = 'pending';

    protected static ?Formatter $formatter = null;

    protected $table = 'community_reviews';

    public $timestamps = true;

    protected $casts = [
        'item_id' => 'integer',
        'user_id' => 'integer',
        'merchant_id' => 'integer',
        'rating' => 'float',
        'previous_rating' => 'float',
        'pros' => 'array',
        'cons' => 'array',
        'is_verified' => 'boolean',
        'helpful_count' => 'integer',
        'comment_count' => 'integer',
        'edited_at' => 'datetime',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    public static function setFormatter(Formatter $formatter): void
    {
        static::$formatter = $formatter;
    }

    /** Stores "General thoughts" the way posts store content: parsed once, rendered on read. */
    public function setBodySource(?string $source, ?User $actor = null): void
    {
        $source = $source === null ? '' : trim($source);
        $this->attributes['body'] = $source === '' || static::$formatter === null ? null : static::$formatter->parse($source, $this, $actor ?? $this->user);
    }

    public function bodySource(): ?string
    {
        $body = $this->attributes['body'] ?? null;

        return $body === null || static::$formatter === null ? null : static::$formatter->unparse($body, $this);
    }

    public function bodyHtml(?ServerRequestInterface $request = null): ?string
    {
        $body = $this->attributes['body'] ?? null;

        return $body === null || static::$formatter === null ? null : static::$formatter->render($body, $this, $request);
    }

    /** @return BelongsTo<Item, $this> */
    public function item(): BelongsTo
    {
        return $this->belongsTo(Item::class, 'item_id');
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** @return BelongsTo<User, $this> */
    public function merchant(): BelongsTo
    {
        return $this->belongsTo(User::class, 'merchant_id');
    }

    /** @return HasMany<Score, $this> */
    public function scores(): HasMany
    {
        return $this->hasMany(Score::class, 'review_id');
    }

    /** @return HasMany<Photo, $this> */
    public function photos(): HasMany
    {
        return $this->hasMany(Photo::class, 'review_id')->orderBy('position')->orderBy('id');
    }

    public function isApproved(): bool
    {
        return $this->status === self::APPROVED;
    }
}
