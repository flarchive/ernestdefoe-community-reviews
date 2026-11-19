<?php

declare(strict_types=1);

namespace ErnestDefoe\CommunityReviews\Api;

use ErnestDefoe\CommunityReviews\Config;
use ErnestDefoe\CommunityReviews\Model\Report;
use Flarum\Api\Context;
use Flarum\Api\Schema;

/**
 * What the forum payload carries: the viewer's abilities (from groups already loaded, no query) and, for
 * moderators only, the number of open reports (one indexed count).
 */
class ForumFields
{
    /** Settings the forum needs, all from the settings already loaded for the request. */
    private const SETTINGS = [
        'int' => ['items_per_page', 'reviews_per_page', 'comments_per_page', 'recent_limit', 'widget_limit', 'name_max', 'card_name_max', 'general_max', 'comment_max', 'rank_min_reviews'],
        'bool' => ['criterion_comment_required', 'ask_price_link', 'require_photo', 'item_approval', 'review_approval', 'one_per_member',
            'index_widget', 'extra_helpful', 'extra_verified', 'extra_pros_cons', 'extra_rich_results'],
        'string' => ['item_order', 'item_order_dir', 'display_order', 'general_label'],
    ];

    public function __construct(private readonly Config $config)
    {
    }

    /** @return list<Schema\Attribute> */
    public function __invoke(): array
    {
        $perm = fn (string $name) => fn (object $forum, Context $context): bool => $context->getActor()->hasPermission('communityReviews.'.$name);

        return [
            Schema\Arr::make('communityReviews')->get(fn (): array => $this->settings()),
            Schema\Boolean::make('communityReviewsCanView')->get($perm('view')),
            Schema\Boolean::make('communityReviewsCanAddItems')->get(fn (object $forum, Context $context): bool => ! $context->getActor()->isGuest() && $context->getActor()->hasPermission('communityReviews.addItems')),
            Schema\Boolean::make('communityReviewsCanWrite')->get(fn (object $forum, Context $context): bool => ! $context->getActor()->isGuest() && $context->getActor()->hasPermission('communityReviews.write')),
            Schema\Boolean::make('communityReviewsCanModerate')->get($perm('moderate')),
            Schema\Integer::make('communityReviewsOpenReports')
                ->visible(fn (object $forum, Context $context): bool => $context->getActor()->hasPermission('communityReviews.moderate'))
                ->get(fn (): int => Report::query()->whereNull('resolved_at')->count()),
        ];
    }

    /** @return array<string, int|bool|string|null> */
    private function settings(): array
    {
        $out = [];
        foreach (self::SETTINGS as $type => $keys) {
            foreach ($keys as $key) {
                $out[$key] = match ($type) {
                    'int' => $this->config->int($key),
                    'bool' => $this->config->bool($key),
                    default => $this->config->string($key),
                };
            }
        }
        $out['max_photos'] = $this->config->maxPhotos();
        // A Client ID is imgur's public identifier for anonymous browser uploads (MyBB puts it in the page too);
        // sent only while the admin has picked imgur.
        $out['imgur_client_id'] = $this->config->imgur() ? $this->config->string('imgur_client_id') : null;
        $out['merchant_group'] = $this->config->merchantGroupId();

        return $out;
    }
}
