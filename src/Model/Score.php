<?php

declare(strict_types=1);

namespace ErnestDefoe\CommunityReviews\Model;

use Flarum\Database\AbstractModel;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One criterion's 1-5 score and comment within a review (MyBB "review field").
 *
 * @property int $id
 * @property int $review_id
 * @property int $criterion_id
 * @property int $score
 * @property string|null $comment
 * @property-read Criterion|null $criterion
 */
class Score extends AbstractModel
{
    protected $table = 'community_review_scores';

    public $timestamps = false;

    protected $casts = [
        'review_id' => 'integer',
        'criterion_id' => 'integer',
        'score' => 'integer',
    ];

    /** @return BelongsTo<Criterion, $this> */
    public function criterion(): BelongsTo
    {
        return $this->belongsTo(Criterion::class, 'criterion_id');
    }
}
