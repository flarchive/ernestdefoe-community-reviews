<?php

declare(strict_types=1);

namespace ErnestDefoe\CommunityReviews;

use ErnestDefoe\CommunityReviews\Model\Item;
use Flarum\User\User;
use Illuminate\Contracts\Cache\Repository as Cache;

/**
 * MyBB's view counter, without counting every refresh: one view per visitor (member, or hashed address for a
 * guest) per item per hour. One cache write and, at most, one single-row increment.
 */
class Views
{
    private const WINDOW_SECONDS = 3600;

    public function __construct(private readonly Cache $cache)
    {
    }

    public function count(Item $item, User $actor, string $ip): void
    {
        $who = $actor->isGuest() ? 'g'.substr(hash('sha256', $ip), 0, 16) : 'u'.$actor->id;
        if ($this->cache->add('community-reviews:view:'.$item->id.':'.$who, 1, self::WINDOW_SECONDS)) {
            Item::query()->whereKey($item->id)->increment('view_count');
        }
    }
}
