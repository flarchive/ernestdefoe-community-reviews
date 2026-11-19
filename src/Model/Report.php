<?php

declare(strict_types=1);

namespace ErnestDefoe\CommunityReviews\Model;

use Carbon\CarbonInterface;
use Flarum\Database\AbstractModel;
use Flarum\User\User;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A member's report of an item, review or comment (MyBB "Report"), for the moderators' queue.
 *
 * @property int $id
 * @property string $kind item|review|comment
 * @property int $target_id
 * @property int|null $item_id
 * @property string|null $excerpt
 * @property int $user_id
 * @property string|null $reason
 * @property CarbonInterface|null $created_at
 * @property CarbonInterface|null $resolved_at
 * @property int|null $resolved_by
 * @property-read User|null $user
 * @property-read Item|null $item
 */
class Report extends AbstractModel
{
    public const SUBJECTS = ['item', 'review', 'comment'];

    protected $table = 'community_review_reports';

    public $timestamps = false;

    protected $casts = [
        'target_id' => 'integer',
        'item_id' => 'integer',
        'user_id' => 'integer',
        'resolved_by' => 'integer',
        'created_at' => 'datetime',
        'resolved_at' => 'datetime',
    ];

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
}
