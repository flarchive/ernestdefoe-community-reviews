<?php

declare(strict_types=1);

/*
 * Community Reviews: a catalog of products members review, feature for feature with MyBB's Community Reviews
 * plugin (categories, per-criterion stars and comments, General thoughts, price paid, link, merchant, photos,
 * product comments, views, merge, reports), with every extra off by default.
 *
 * Every read goes through one visibility rule per model (Access\*Visibility), every write through the JSON:API
 * resources in Api\ and the writers in Write\. Counts and averages live on the item row (Aggregates, queued).
 *
 * 🚨 String literals only below, never one of our class constants read at load time: it would autoload during
 * boot and fatal every request while a deploy swaps composer's autoload map.
 */

use ErnestDefoe\CommunityReviews\Access;
use ErnestDefoe\CommunityReviews\Api;
use ErnestDefoe\CommunityReviews\Http;
use ErnestDefoe\CommunityReviews\Model\Comment;
use ErnestDefoe\CommunityReviews\Model\Item;
use ErnestDefoe\CommunityReviews\Model\Review;
use ErnestDefoe\CommunityReviews\ServiceProvider;
use Flarum\Api\Resource\ForumResource;
use Flarum\Extend;
use Flarum\Foundation\Paths;

return [
    (new Extend\Frontend('forum'))
        ->js(__DIR__.'/js/dist/forum.js')
        ->jsDirectory(__DIR__.'/js/dist/forum')
        ->css(__DIR__.'/less/forum.less')
        ->route('/reviews', 'community-reviews.index')
        ->route('/reviews/c/{slug}', 'community-reviews.category')
        ->route('/reviews/i/{id:\d+(?:-[^/]*)?}', 'community-reviews.item', Http\ItemHead::class)
        ->route('/reviews/reports', 'community-reviews.reports')
        ->route('/u/{username}/reviews', 'user.community-reviews'),

    (new Extend\Frontend('admin'))
        ->js(__DIR__.'/js/dist/admin.js')
        ->css(__DIR__.'/less/admin.less'),

    new Extend\Locales(__DIR__.'/locale'),

    (new Extend\Routes('forum'))
        ->get('/reviews/photo/{id:\d+}', 'community-reviews.photo', Http\PhotoController::class),

    (new Extend\Filesystem())
        ->disk('ernestdefoe-community-reviews', fn (Paths $paths): array => [
            'root' => $paths->storage.'/community-reviews',
            'visibility' => 'private',
        ]),

    new Extend\ApiResource(Api\CategoryResource::class),
    new Extend\ApiResource(Api\CriterionResource::class),
    new Extend\ApiResource(Api\ItemResource::class),
    new Extend\ApiResource(Api\ReviewResource::class),
    new Extend\ApiResource(Api\CommentResource::class),
    new Extend\ApiResource(Api\PhotoResource::class),
    new Extend\ApiResource(Api\ReportResource::class),

    (new Extend\ApiResource(ForumResource::class))
        ->fields(Api\ForumFields::class),

    (new Extend\ModelVisibility(Item::class))->scope(Access\ItemVisibility::class),
    (new Extend\ModelVisibility(Review::class))->scope(Access\ReviewVisibility::class),
    (new Extend\ModelVisibility(Comment::class))->scope(Access\CommentVisibility::class),

    (new Extend\Policy())
        ->modelPolicy(Item::class, Access\ItemPolicy::class)
        ->modelPolicy(Review::class, Access\ReviewPolicy::class)
        ->modelPolicy(Comment::class, Access\CommentPolicy::class),

    (new Extend\ServiceProvider())
        ->register(ServiceProvider::class),

    (new Extend\ThrottleApi())
        ->set('community-reviews', \ErnestDefoe\CommunityReviews\Throttle::class),

    (new Extend\Notification())
        ->type(\ErnestDefoe\CommunityReviews\Notification\MerchantTaggedBlueprint::class, ['alert'])
        ->type(\ErnestDefoe\CommunityReviews\Notification\NewReviewBlueprint::class, ['alert'])
        ->type(\ErnestDefoe\CommunityReviews\Notification\NewCommentBlueprint::class, ['alert']),

    (new Extend\Event())
        ->listen(\ErnestDefoe\CommunityReviews\Event\ReviewSaved::class, [\ErnestDefoe\CommunityReviews\Listener\QueueNotifications::class, 'review'])
        ->listen(\ErnestDefoe\CommunityReviews\Event\CommentSaved::class, [\ErnestDefoe\CommunityReviews\Listener\QueueNotifications::class, 'comment'])
        ->listen('eloquent.deleting: ErnestDefoe\\CommunityReviews\\Model\\Review', [\ErnestDefoe\CommunityReviews\Listener\DeletePhotos::class, 'review'])
        ->listen('eloquent.deleting: ErnestDefoe\\CommunityReviews\\Model\\Item', [\ErnestDefoe\CommunityReviews\Listener\DeletePhotos::class, 'item'])
        ->listen(\Flarum\User\Event\Deleting::class, [\ErnestDefoe\CommunityReviews\Listener\DeletePhotos::class, 'user']),

    (new Extend\Console())
        ->command(\ErnestDefoe\CommunityReviews\Console\PruneCommand::class)
        ->schedule(\ErnestDefoe\CommunityReviews\Console\PruneCommand::class, function (\Illuminate\Console\Scheduling\Event $event): void {
            $event->daily()->withoutOverlapping();
        }),

    // A member's reviews, comments and photos in their data export; anonymised or erased with them.
    (new Extend\Conditional())
        ->whenExtensionEnabled('flarum-gdpr', fn (): array => [
            (new \Flarum\Gdpr\Extend\UserData())->addType(\ErnestDefoe\CommunityReviews\Gdpr\ReviewsData::class),
        ]),

    // Out of the box this is MyBB's feature set: every extra is off.
    (new Extend\Settings())
        ->default('ernestdefoe-community-reviews.item_order', 'name')
        ->default('ernestdefoe-community-reviews.item_order_dir', 'asc')
        ->default('ernestdefoe-community-reviews.display_order', 'desc')
        ->default('ernestdefoe-community-reviews.items_per_page', 12)
        ->default('ernestdefoe-community-reviews.reviews_per_page', 6)
        ->default('ernestdefoe-community-reviews.comments_per_page', 6)
        ->default('ernestdefoe-community-reviews.recent_limit', 12)
        ->default('ernestdefoe-community-reviews.widget_limit', 6)
        ->default('ernestdefoe-community-reviews.name_max', 100)
        ->default('ernestdefoe-community-reviews.card_name_max', 30)
        ->default('ernestdefoe-community-reviews.general_label', '')
        ->default('ernestdefoe-community-reviews.general_max', 2000)
        ->default('ernestdefoe-community-reviews.comment_max', 2000)
        ->default('ernestdefoe-community-reviews.criterion_comment_required', true)
        ->default('ernestdefoe-community-reviews.ask_price_link', true)
        ->default('ernestdefoe-community-reviews.max_photos', 4)
        ->default('ernestdefoe-community-reviews.require_photo', false)
        ->default('ernestdefoe-community-reviews.photo_host', 'local')
        ->default('ernestdefoe-community-reviews.imgur_client_id', '')
        ->default('ernestdefoe-community-reviews.merchant_group', 0)
        ->default('ernestdefoe-community-reviews.item_approval', false)
        ->default('ernestdefoe-community-reviews.review_approval', false)
        ->default('ernestdefoe-community-reviews.one_per_member', false)
        ->default('ernestdefoe-community-reviews.rank_min_reviews', 3)
        ->default('ernestdefoe-community-reviews.flood_seconds', 10)
        ->default('ernestdefoe-community-reviews.index_widget', false)
        ->default('ernestdefoe-community-reviews.extra_helpful', false)
        ->default('ernestdefoe-community-reviews.extra_verified', false)
        ->default('ernestdefoe-community-reviews.extra_pros_cons', false)
        ->default('ernestdefoe-community-reviews.extra_rich_results', false),
];
