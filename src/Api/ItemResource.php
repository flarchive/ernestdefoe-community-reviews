<?php

declare(strict_types=1);

namespace ErnestDefoe\CommunityReviews\Api;

use ErnestDefoe\CommunityReviews\Config;
use ErnestDefoe\CommunityReviews\Model\Category;
use ErnestDefoe\CommunityReviews\Model\Item;
use ErnestDefoe\CommunityReviews\Model\Review;
use ErnestDefoe\CommunityReviews\Photo\Urls;
use ErnestDefoe\CommunityReviews\Stats;
use ErnestDefoe\CommunityReviews\Views;
use ErnestDefoe\CommunityReviews\Write\Fail;
use ErnestDefoe\CommunityReviews\Write\ItemWriter;
use ErnestDefoe\CommunityReviews\Write\Merger;
use ErnestDefoe\CommunityReviews\Write\ReviewWriter;
use Flarum\Api\Context;
use Flarum\Api\Endpoint;
use Flarum\Api\Resource\AbstractDatabaseResource;
use Flarum\Api\Schema;
use Flarum\Api\Sort\SortColumn;
use Flarum\Http\RequestUtil;
use Illuminate\Database\ConnectionInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Laminas\Diactoros\Response\EmptyResponse;
use Laminas\Diactoros\Response\JsonResponse;
use Tobyz\JsonApiServer\Context as BaseContext;
use Tobyz\JsonApiServer\Pagination\OffsetPagination;
use Tobyz\JsonApiServer\Pagination\Pagination;

/**
 * The `review-items` type (MyBB "products").
 *
 * GET    /api/review-items   filters: category (includes its sub-categories), q (name search), user (added by),
 *                            status=pending (moderators); sort: name, rating, reviewCount, viewCount, createdAt,
 *                            lastActivityAt, rank (each with -), default from the admin's settings.
 * GET    /api/review-items/{id}
 * POST   /api/review-items   {categoryId, name, brand?, fields?, review: {…first review…}} (MyBB adds both at once)
 * PATCH  /api/review-items/{id}        DELETE /api/review-items/{id}
 * POST   /api/review-items/{id}/merge  {"targetId": N}   moderators (MyBB "Merge products")
 * POST   /api/review-items/{id}/view   counts a view (once per visitor per hour)
 *
 * @extends AbstractDatabaseResource<Item>
 */
class ItemResource extends AbstractDatabaseResource
{
    use Concerns\ReadsInput;

    private const SORTS = [
        'name' => 'name',
        'rating' => 'rating',
        'reviewCount' => 'review_count',
        'viewCount' => 'view_count',
        'createdAt' => 'created_at',
        'lastActivityAt' => 'last_activity_at',
        'rank' => 'rating_rank',
    ];

    private const DEFAULT_SORT = ['name' => 'name', 'rating' => 'rating', 'reviews' => 'reviewCount', 'views' => 'viewCount', 'date' => 'createdAt'];

    /** @var array<int, Review> the first review waiting for its item to be saved, per item object id */
    private array $firstReviews = [];

    /** Built on every API request: constructors only, no queries or settings reads. */
    public function __construct(
        private readonly Config $config,
        private readonly ItemWriter $writer,
        private readonly ReviewWriter $reviews,
        private readonly Merger $merger,
        private readonly Views $views,
        private readonly Stats $stats,
        private readonly Urls $urls,
        private readonly Fail $fail,
        private readonly ConnectionInterface $db,
    ) {
    }

    public function type(): string
    {
        return 'review-items';
    }

    public function model(): string
    {
        return Item::class;
    }

    /** @param Builder<Item> $query */
    public function scope(Builder $query, BaseContext $context): void
    {
        $query->whereVisibleTo($context->getActor())->select('community_review_items.*')->with(['thumbnail', 'category']);
    }

    public function endpoints(): array
    {
        return [
            Endpoint\Index::make()
                ->defaultInclude(['category'])
                ->paginate(12, 50)
                ->query(fn (Builder $query, ?Pagination $pagination, Context $context, array $filters, ?array $sort) => $this->listing($query, $pagination, $context, $filters, $sort)),
            // MyBB's Statistics block: catalog totals over approved items, cached (Stats). GET /api/review-items/stats
            Endpoint\Endpoint::make('stats')
                ->route('GET', '/stats')
                ->can('communityReviews.view')
                ->action(fn (): array => $this->stats->get())
                ->response(fn (Context $context, array $stats) => new JsonResponse(['data' => ['type' => 'review-stats', 'id' => '1', 'attributes' => $stats]])),
            Endpoint\Show::make()->defaultInclude(['category', 'category.criteria', 'user']),
            Endpoint\Create::make()->authenticated()->can('communityReviews.addItems')->defaultInclude(['category']),
            Endpoint\Update::make()->authenticated()->can('edit')->defaultInclude(['category']),
            Endpoint\Delete::make()->authenticated()->can('delete'),
            Endpoint\Endpoint::make('merge')
                ->route('POST', '/{id}/merge')
                ->authenticated()
                ->can('moderate')
                ->action(function (Context $context): Item {
                    /** @var Item $source */
                    $source = $context->model;
                    $target = Item::whereVisibleTo($context->getActor())->find((int) ($context->body()['targetId'] ?? 0));
                    if ($target === null) {
                        $this->fail->on('targetId', 'merge_target_missing');
                    }

                    return $this->merger->merge($source, $target);
                }),
            Endpoint\Endpoint::make('view')
                ->route('POST', '/{id}/view')
                ->action(function (Context $context): void {
                    /** @var Item $item */
                    $item = $context->model;
                    $this->views->count($item, $context->getActor(), (string) $context->request->getAttribute('ipAddress', ''));
                })
                ->response(fn () => new EmptyResponse(204)),
        ];
    }

    public function fields(): array
    {
        $can = fn (string $ability) => fn (Item $item, Context $context): bool => $context->getActor()->can($ability, $item);

        return [
            // Write-only inputs: ItemWriter applies them together (creating / updating below).
            Schema\Integer::make('categoryId')->visible(false)->writable()->set(fn () => null),
            Schema\Str::make('name')->writable()->set(fn () => null),
            Schema\Str::make('brand')->nullable()->writable()->set(fn () => null),
            Schema\Arr::make('fields')->nullable()->writable()->set(fn () => null),
            Schema\Arr::make('review')->visible(false)->writableOnCreate()->set(fn () => null),

            Schema\Str::make('slug'),
            Schema\Str::make('status')->writable(fn (Item $item, Context $context) => $context->getActor()->can('moderate', $item))->set(fn () => null)
                ->visible(fn (Item $item, Context $context): bool => $item->status !== Item::APPROVED || $context->getActor()->can('moderate', $item)),
            Schema\Integer::make('reviewCount')->property('review_count'),
            Schema\Integer::make('commentCount')->property('comment_count'),
            Schema\Integer::make('viewCount')->property('view_count'),
            Schema\Number::make('rating')->nullable(),
            Schema\Number::make('rank')->property('rating_rank')->nullable(),
            Schema\Arr::make('criteriaAverages')->property('criteria_avg')->nullable(),
            Schema\Arr::make('distribution')->nullable(),
            Schema\Arr::make('topMentions')->property('top_mentions')->nullable()->visible(fn (): bool => $this->config->extra('pros_cons')),
            Schema\Str::make('thumbnailUrl')->nullable()->get(fn (Item $item): ?string => $item->thumbnail ? $this->urls->thumb($item->thumbnail) : null),
            Schema\Str::make('photoUrl')->nullable()->get(fn (Item $item): ?string => $item->thumbnail ? $this->urls->full($item->thumbnail) : null),
            Schema\DateTime::make('createdAt')->property('created_at'),
            Schema\DateTime::make('lastActivityAt')->property('last_activity_at')->nullable(),

            Schema\Boolean::make('canEdit')->get($can('edit')),
            Schema\Boolean::make('canDelete')->get($can('delete')),
            Schema\Boolean::make('canModerate')->get($can('moderate')),
            Schema\Boolean::make('canReview')->get($can('review')),
            Schema\Boolean::make('canComment')->get($can('comment')),
            Schema\Boolean::make('canReport')->get($can('report')),

            Schema\Relationship\ToOne::make('category')->type('review-categories')->includable(),
            Schema\Relationship\ToOne::make('user')->type('users')->includable(),
        ];
    }

    /** @return list<SortColumn> */
    public function sorts(): array
    {
        return array_map(fn (string $name) => SortColumn::make($name), array_keys(self::SORTS));
    }

    /** @param Context $context */
    public function creating(object $model, BaseContext $context): ?object
    {
        $actor = $context->getActor();
        $input = $this->attributes($context);
        $this->writer->prepare($model, $actor, $input);

        // MyBB adds a product with its first review. Moderators may add an empty item (to build the catalog).
        $first = is_array($input['review'] ?? null) ? $input['review'] : null;
        if ($first === null && ! $actor->hasPermission('communityReviews.moderate')) {
            $this->fail->on('review', 'first_review_missing');
        }
        if ($first !== null) {
            $review = new Review();
            $this->reviews->prepare($review, $model, $actor, $first, $this->ip($context));
            $this->firstReviews[spl_object_id($model)] = $review;
        }

        return $model;
    }

    /** @param Context $context */
    public function updating(object $model, BaseContext $context): ?object
    {
        $this->writer->prepare($model, $context->getActor(), $this->attributes($context));

        return $model;
    }

    protected function saveModel(Model $model, BaseContext $context): void
    {
        $this->db->transaction(function () use ($model, $context): void {
            parent::saveModel($model, $context);
            $review = $this->firstReviews[spl_object_id($model)] ?? null;
            unset($this->firstReviews[spl_object_id($model)]);
            if ($review instanceof Review && $model instanceof Item) {
                $review->item_id = $model->id;
                $review->save();
                $this->reviews->saveChildren($review);
                $this->reviews->saved($review, $context->getActor(), true, null);
            }
        });
        if ($model instanceof Item) {
            $this->writer->saved($model);
        }
    }

    /** @param Context $context */
    public function deleting(object $model, BaseContext $context): void
    {
        if ($model instanceof Item) {
            $this->writer->deleted($model);
        }
    }

    /**
     * @param Builder<Item> $query
     * @param array<string, mixed> $filters
     * @param array<string, string>|null $sort
     */
    private function listing(Builder $query, ?Pagination $pagination, Context $context, array $filters, ?array $sort): Context
    {
        $actor = $context->getActor();

        if (isset($filters['category'])) {
            $id = (int) $filters['category'];
            $query->whereIn('community_review_items.category_id', Category::query()->whereKey($id)->orWhere('parent_id', $id)->select('id'));
        }
        if (isset($filters['user'])) {
            $query->where('community_review_items.user_id', (int) $filters['user']);
        }
        if (($filters['status'] ?? null) === 'pending' && $actor->hasPermission('communityReviews.moderate')) {
            $query->where('community_review_items.status', Item::PENDING);
        }
        $q = trim((string) ($filters['q'] ?? ''));
        if ($q !== '') {
            $this->search($query, mb_substr($q, 0, 100));
        }

        $this->order($query, $sort);
        if ($pagination instanceof OffsetPagination) {
            $pagination->apply($query);
        }

        return $context->withQuery($query);
    }

    /**
     * MyBB's FULLTEXT name search (MySQL and PostgreSQL), plus a name-prefix match that uses the plain name index:
     * InnoDB adds rows to a FULLTEXT index only at commit and skips short words, so a product added a moment ago or
     * called "XL" is still found by its first letters. SQLite has no FULLTEXT: the prefix match alone.
     */
    private function search(Builder $query, string $q): void
    {
        $prefix = addcslashes($q, '%_\\').'%';
        // Case-insensitive everywhere: PostgreSQL's LIKE isn't, so it gets ILIKE.
        $like = $this->db->getDriverName() === 'pgsql' ? 'ilike' : 'like';
        if ($this->db->getDriverName() === 'sqlite') {
            $query->where('community_review_items.name', $like, $prefix);

            return;
        }
        $query->where(fn (Builder $w) => $w->where('community_review_items.name', $like, $prefix)
            ->orWhereFullText('community_review_items.name', $q));
    }

    /**
     * @param Builder<Item> $query
     * @param array<string, string>|null $sort
     */
    private function order(Builder $query, ?array $sort): void
    {
        if (! $sort) {
            $sort = [self::DEFAULT_SORT[$this->config->itemOrder()] => $this->config->itemOrderDescending() ? 'desc' : 'asc'];
        }
        $grammar = $this->db->getQueryGrammar();
        foreach ($sort as $name => $direction) {
            $column = 'community_review_items.'.(self::SORTS[$name] ?? 'name');
            if (in_array($name, ['rating', 'rank', 'lastActivityAt'], true)) {
                // Unrated items last whichever way (PostgreSQL would put NULLs first when descending).
                $query->orderByRaw($grammar->wrap($column).' IS NULL');
            }
            $query->orderBy($column, $direction === 'desc' ? 'desc' : 'asc');
        }
        $query->orderBy('community_review_items.id', 'desc');
    }
}
