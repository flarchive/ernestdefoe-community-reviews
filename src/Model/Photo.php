<?php

declare(strict_types=1);

namespace ErnestDefoe\CommunityReviews\Model;

use Carbon\CarbonInterface;
use Flarum\Database\AbstractModel;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A review photo: a re-encoded file on our disk (path, thumb_path), or an imgur link (url, thumb_url) when the
 * admin chose imgur. review_id stays null until the review that uses it is saved.
 *
 * @property int $id
 * @property int|null $review_id
 * @property int $user_id
 * @property string|null $path
 * @property string|null $thumb_path
 * @property string|null $url
 * @property string|null $thumb_url
 * @property int $width
 * @property int $height
 * @property int $position
 * @property CarbonInterface|null $created_at
 * @property-read Review|null $review
 */
class Photo extends AbstractModel
{
    protected $table = 'community_review_photos';

    public $timestamps = false;

    protected $casts = [
        'review_id' => 'integer',
        'user_id' => 'integer',
        'width' => 'integer',
        'height' => 'integer',
        'position' => 'integer',
        'created_at' => 'datetime',
    ];

    /** @return BelongsTo<Review, $this> */
    public function review(): BelongsTo
    {
        return $this->belongsTo(Review::class, 'review_id');
    }

    public function isLocal(): bool
    {
        return $this->path !== null;
    }
}
