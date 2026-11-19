<?php

declare(strict_types=1);

namespace ErnestDefoe\CommunityReviews\Model;

use Carbon\CarbonInterface;
use Flarum\Database\AbstractModel;
use Flarum\Database\ScopeVisibilityTrait;
use Flarum\User\User;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A reviewed product (MyBB "product"). Its counts, averages and thumbnail are kept on the row by
 * Aggregates, so a list never computes them. Who sees it: Access\ItemVisibility.
 *
 * @property int $id
 * @property int $category_id
 * @property int|null $user_id
 * @property string $name
 * @property string $slug
 * @property string|null $brand
 * @property array<string, mixed>|null $fields
 * @property string $status
 * @property int $review_count
 * @property int $comment_count
 * @property int $view_count
 * @property float|null $rating
 * @property float|null $rating_rank
 * @property array<string, float>|null $criteria_avg
 * @property list<int>|null $distribution
 * @property array<string, mixed>|null $top_mentions
 * @property int|null $thumbnail_photo_id
 * @property CarbonInterface|null $last_activity_at
 * @property CarbonInterface|null $created_at
 * @property CarbonInterface|null $updated_at
 * @property-read Category|null $category
 * @property-read User|null $user
 * @property-read Photo|null $thumbnail
 */
class Item extends AbstractModel
{
    use ScopeVisibilityTrait;

    public const APPROVED = 'approved';

    public const PENDING = 'pending';

    protected $table = 'community_review_items';

    public $timestamps = true;

    protected $casts = [
        'category_id' => 'integer',
        'user_id' => 'integer',
        'fields' => 'array',
        'review_count' => 'integer',
        'comment_count' => 'integer',
        'view_count' => 'integer',
        'rating' => 'float',
        'rating_rank' => 'float',
        'criteria_avg' => 'array',
        'distribution' => 'array',
        'top_mentions' => 'array',
        'thumbnail_photo_id' => 'integer',
        'last_activity_at' => 'datetime',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    /** @return BelongsTo<Category, $this> */
    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class, 'category_id');
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** @return BelongsTo<Photo, $this> */
    public function thumbnail(): BelongsTo
    {
        return $this->belongsTo(Photo::class, 'thumbnail_photo_id');
    }

    public function isApproved(): bool
    {
        return $this->status === self::APPROVED;
    }
}
