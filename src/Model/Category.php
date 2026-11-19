<?php

declare(strict_types=1);

namespace ErnestDefoe\CommunityReviews\Model;

use Carbon\CarbonInterface;
use Flarum\Database\AbstractModel;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A review category (MyBB "category"): flat by default, optionally one level of children.
 *
 * @property int $id
 * @property int|null $parent_id
 * @property string $name
 * @property string $slug
 * @property string|null $description
 * @property string|null $icon
 * @property string|null $color
 * @property int $position
 * @property list<array<string, mixed>>|null $item_fields
 * @property int $item_count
 * @property int $review_count
 * @property CarbonInterface|null $created_at
 * @property-read Category|null $parent
 * @property-read \Illuminate\Database\Eloquent\Collection<int, Criterion> $criteria
 */
class Category extends AbstractModel
{
    protected $table = 'community_review_categories';

    public $timestamps = false;

    protected $casts = [
        'parent_id' => 'integer',
        'position' => 'integer',
        'item_fields' => 'array',
        'item_count' => 'integer',
        'review_count' => 'integer',
        'created_at' => 'datetime',
    ];

    /** @return BelongsTo<Category, $this> */
    public function parent(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_id');
    }

    /** @return HasMany<Criterion, $this> */
    public function criteria(): HasMany
    {
        return $this->hasMany(Criterion::class, 'category_id')->orderBy('position')->orderBy('id');
    }
}
