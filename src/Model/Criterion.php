<?php

declare(strict_types=1);

namespace ErnestDefoe\CommunityReviews\Model;

use Flarum\Database\AbstractModel;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * What every review in a category rates 1-5 and comments on (MyBB "field").
 *
 * @property int $id
 * @property int $category_id
 * @property string $name
 * @property int $weight
 * @property int $position
 * @property-read Category|null $category
 */
class Criterion extends AbstractModel
{
    protected $table = 'community_review_criteria';

    public $timestamps = false;

    protected $casts = [
        'category_id' => 'integer',
        'weight' => 'integer',
        'position' => 'integer',
    ];

    /** @return BelongsTo<Category, $this> */
    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class, 'category_id');
    }
}
