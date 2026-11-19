<?php

declare(strict_types=1);

namespace ErnestDefoe\CommunityReviews;

use Carbon\Carbon;
use ErnestDefoe\CommunityReviews\Model\Comment;
use ErnestDefoe\CommunityReviews\Model\Item;
use ErnestDefoe\CommunityReviews\Model\Photo;
use ErnestDefoe\CommunityReviews\Model\Report;
use ErnestDefoe\CommunityReviews\Model\Review;
use Flarum\Http\RequestUtil;
use Psr\Http\Message\ServerRequestInterface;

/**
 * A flood guard (MyBB has none; core throttles posts the same way): a member adds one product, review, comment or
 * report every `flood_seconds` (default 10, 0 turns it off), and at most twelve photos a minute (the picker uploads several at once). Moderators are
 * exempt. Each check is one indexed EXISTS or COUNT on the member's own rows, only on these write routes.
 */
class Throttle
{
    private const PHOTOS_PER_MINUTE = 12;

    /** @var array<string, class-string<\Flarum\Database\AbstractModel>> */
    private const WRITES = [
        'review-items.create' => Item::class,
        'reviews.create' => Review::class,
        'review-comments.create' => Comment::class,
        'review-reports.create' => Report::class,
    ];

    public function __construct(private readonly Config $config)
    {
    }

    public function __invoke(ServerRequestInterface $request): ?bool
    {
        $route = $request->getAttribute('routeName');
        if (! is_string($route) || (! isset(self::WRITES[$route]) && $route !== 'review-photos.upload')) {
            return null;
        }

        $actor = RequestUtil::getActor($request);
        $seconds = $this->config->int('flood_seconds');
        if ($seconds < 1 || $actor->isGuest() || $actor->hasPermission('communityReviews.moderate')) {
            return null;
        }

        if ($route === 'review-photos.upload') {
            return Photo::query()->where('user_id', $actor->id)->where('created_at', '>', Carbon::now()->subMinute())->count() >= self::PHOTOS_PER_MINUTE;
        }

        $model = self::WRITES[$route];

        return $model::query()->where('user_id', $actor->id)->where('created_at', '>', Carbon::now()->subSeconds($seconds))->exists();
    }
}
