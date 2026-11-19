<?php

declare(strict_types=1);

namespace ErnestDefoe\CommunityReviews\Api;

use Carbon\Carbon;
use ErnestDefoe\CommunityReviews\Config;
use ErnestDefoe\CommunityReviews\Model\Item;
use ErnestDefoe\CommunityReviews\Model\Photo;
use ErnestDefoe\CommunityReviews\Model\Review;
use ErnestDefoe\CommunityReviews\Model\Score;
use ErnestDefoe\CommunityReviews\Photo\Urls;
use ErnestDefoe\CommunityReviews\Write\Fail;
use ErnestDefoe\CommunityReviews\Write\ReviewWriter;
use Flarum\Api\Context;
use Flarum\Api\Endpoint;
use Flarum\Api\Resource\AbstractDatabaseResource;
use Flarum\Api\Schema;
use Flarum\Api\Sort\SortColumn;
use Illuminate\Database\ConnectionInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Query\Builder as QueryBuilder;
use Laminas\Diactoros\Response\JsonResponse;
use Tobyz\JsonApiServer\Context as BaseContext;
use Tobyz\JsonApiServer\Pagination\OffsetPagination;
use Tobyz\JsonApiServer\Pagination\Pagination;

/**
 * The `reviews` type.
 *
 * GET    /api/reviews   filters: item, user (written by), merchant (tagged as merchant), status=pending (moderators),
 *                       photos=1; sort: createdAt (default: the admin's display order), helpful.
 * GET    /api/reviews/{id}
 * POST   /api/reviews   {scores: [{criterionId, score, comment}], title?, body?, price?, url?, merchantName?, photos?: [ids],
 *                       ownedFor?, pros?, cons?, relationships.item}
 * PATCH  /api/reviews/{id}   the same attributes; moderators also status, isVerified
 * DELETE /api/reviews/{id}
 * POST   /api/reviews/{id}/helpful  {"helpful": true|false}  (extra, off by default)
 * GET    /api/reviews/merchant-summary?filter[merchant]=N   a merchant's average and count
 *
 * @extends AbstractDatabaseResource<Review>
 */
class ReviewResource extends AbstractDatabaseResource
{
    use Concerns\ReadsInput;

    private const INPUTS = ['scores', 'title', 'body', 'price', 'url', 'merchantName', 'photos', 'ownedFor', 'pros', 'cons', 'status', 'isVerified'];

    /** @var array<int, int|null> the merchant before an edit, per review object id */
    private array $previousMerchant = [];

    public function __construct(
        private readonly Config $config,
        private readonly ReviewWriter $writer,
        private readonly Urls $urls,
        private readonly Fail $fail,
        private readonly ConnectionInterface $db,
    ) {
    }

    public function type(): string
    {
        return 'reviews';
    }

    public function model(): string
    {
        return Review::class;
    }

    /** @param Builder<Review> $query */
    public function scope(Builder $query, BaseContext $context): void
    {
        $actor = $context->getActor();
        $query->whereVisibleTo($actor)->select('community_reviews.*')
            ->with(['user.groups', 'merchant', 'scores.criterion', 'photos', 'item']);

        if (! $actor->isGuest() && $this->config->extra('helpful')) {
            $query->addSelect(['voted' => fn (QueryBuilder $q) => $q->selectRaw('1')->from('community_review_votes')
                ->whereColumn('community_review_votes.review_id', 'community_reviews.id')
                ->where('community_review_votes.user_id', $actor->id)->limit(1)]);
        }
    }

    public function endpoints(): array
    {
        return [
            Endpoint\Index::make()
                ->defaultInclude(['user', 'merchant'])
                ->paginate(6, 50)
                ->query(fn (Builder $query, ?Pagination $pagination, Context $context, array $filters, ?array $sort) => $this->listing($query, $pagination, $context, $filters, $sort)),
            // counts): GET /api/reviews/merchant-summary?filter[merchant]=N. Two aggregates.
            Endpoint\Endpoint::make('merchantSummary')
                ->route('GET', '/merchant-summary')
                ->action(fn (Context $context): array => $this->merchantSummary($context))
                ->response(fn (Context $context, array $summary) => new JsonResponse(['data' => ['type' => 'review-merchants', 'id' => (string) $summary['user'], 'attributes' => $summary]])),
            Endpoint\Show::make()->defaultInclude(['user', 'merchant', 'item']),
            Endpoint\Create::make()->authenticated()->can('communityReviews.write')->defaultInclude(['user', 'merchant', 'item']),
            Endpoint\Update::make()->authenticated()->can('edit')->defaultInclude(['user', 'merchant']),
            Endpoint\Delete::make()->authenticated()->can('delete'),
            // A merchant's average and count, over the reviews the viewer can see (a held or gated review never
            Endpoint\Endpoint::make('helpful')
                ->route('POST', '/{id}/helpful')
                ->authenticated()
                ->can('vote')
                ->action(fn (Context $context): ?Review => $this->vote($context)),
        ];
    }

    public function fields(): array
    {
        $insider = fn (Review $r, Context $context): bool => $context->getActor()->can('edit', $r);
        $can = fn (string $ability) => fn (Review $r, Context $context): bool => $context->getActor()->can($ability, $r);
        $input = fn (Schema\Attribute $field) => $field->writable()->set(fn () => null);

        return [
            $input(Schema\Arr::make('scores')->get(fn (Review $r): array => $this->scores($r))),
            $input(Schema\Str::make('title')->nullable()),
            $input(Schema\Str::make('body')->nullable()->visible($insider)->get(fn (Review $r): ?string => $r->bodySource())),
            Schema\Str::make('bodyHtml')->nullable()->get(fn (Review $r, Context $context): ?string => $r->bodyHtml($context->request)),
            $input(Schema\Str::make('price')->nullable()),
            $input(Schema\Str::make('url')->nullable()),
            // The merchant's username, written by the composer; read back through the `merchant` relationship.
            $input(Schema\Str::make('merchantName')->nullable()->visible(false)),
            $input(Schema\Arr::make('photos')->get(fn (Review $r): array => $r->photos->map(fn (Photo $p) => $this->urls->of($p))->values()->all())),
            $input(Schema\Str::make('ownedFor')->property('owned_for')->nullable()->visible(fn (): bool => $this->config->extra('verified'))),
            $input(Schema\Arr::make('pros')->nullable()->visible(fn (): bool => $this->config->extra('pros_cons'))),
            $input(Schema\Arr::make('cons')->nullable()->visible(fn (): bool => $this->config->extra('pros_cons'))),
            $input(Schema\Boolean::make('isVerified')->property('is_verified')->visible(fn (): bool => $this->config->extra('verified'))),
            $input(Schema\Str::make('status')->visible(fn (Review $r, Context $context): bool => $r->status !== Review::APPROVED || $context->getActor()->can('moderate', $r))),

            // For notifications and lists that show the review away from its item page: the item's link and name,
            // from the eager-loaded item (the review being visible means its item is).
            Schema\Str::make('itemPath')->nullable()->get(fn (Review $r): ?string => $r->item ? $r->item->id.'-'.$r->item->slug : null),
            Schema\Str::make('itemName')->nullable()->get(fn (Review $r): ?string => $r->item?->name),
            Schema\Number::make('rating'),
            Schema\Number::make('previousRating')->property('previous_rating')->nullable(),
            Schema\Integer::make('helpfulCount')->property('helpful_count')->visible(fn (): bool => $this->config->extra('helpful')),
            Schema\Boolean::make('votedHelpful')->visible(fn (): bool => $this->config->extra('helpful'))->get(fn (Review $r): bool => (bool) ($r->getAttribute('voted') ?? false)),
            Schema\Integer::make('commentCount')->property('comment_count'),
            Schema\DateTime::make('createdAt')->property('created_at'),
            Schema\DateTime::make('editedAt')->property('edited_at')->nullable(),

            Schema\Boolean::make('canEdit')->get($can('edit')),
            Schema\Boolean::make('canDelete')->get($can('delete')),
            Schema\Boolean::make('canModerate')->get($can('moderate')),
            Schema\Boolean::make('canVote')->get($can('vote')),
            Schema\Boolean::make('canReport')->get($can('report')),

            Schema\Relationship\ToOne::make('item')->type('review-items')->includable()->requiredOnCreate()->writableOnCreate(),
            Schema\Relationship\ToOne::make('user')->type('users')->includable(),
            Schema\Relationship\ToOne::make('merchant')->type('users')->includable(),
        ];
    }

    /** @return list<SortColumn> */
    public function sorts(): array
    {
        return [SortColumn::make('createdAt'), SortColumn::make('helpful'), SortColumn::make('rating')];
    }

    /** @param Context $context */
    public function creating(object $model, BaseContext $context): ?object
    {
        $item = Item::whereVisibleTo($context->getActor())->find($model->item_id);
        if ($item === null) {
            $this->fail->on('item', 'item_missing');
        }
        $this->writer->prepare($model, $item, $context->getActor(), array_intersect_key($this->attributes($context), array_flip(self::INPUTS)), $this->ip($context));

        return $model;
    }

    /** @param Context $context */
    public function updating(object $model, BaseContext $context): ?object
    {
        /** @var Review $model */
        $this->previousMerchant[spl_object_id($model)] = $model->getOriginal('merchant_id');
        $item = Item::query()->findOrFail($model->item_id);
        $this->writer->prepare($model, $item, $context->getActor(), array_intersect_key($this->attributes($context), array_flip(self::INPUTS)), null);

        return $model;
    }

    protected function saveModel(Model $model, BaseContext $context): void
    {
        $isNew = ! $model->exists;
        $this->db->transaction(function () use ($model, $context): void {
            parent::saveModel($model, $context);
            if ($model instanceof Review) {
                $this->writer->saveChildren($model);
            }
        });
        if ($model instanceof Review) {
            $previous = $this->previousMerchant[spl_object_id($model)] ?? null;
            unset($this->previousMerchant[spl_object_id($model)]);
            $this->writer->saved($model, $context->getActor(), $isNew, $previous);
        }
    }

    /** @param Context $context */
    public function deleting(object $model, BaseContext $context): void
    {
        if ($model instanceof Review) {
            $this->writer->deleted($model);
        }
    }

    /** @return list<array{criterionId: int, name: string, score: int, comment: string|null}> in the category's order */
    private function scores(Review $review): array
    {
        return $review->scores
            ->sortBy(fn (Score $s) => [$s->criterion->position ?? 0, $s->criterion_id])
            ->map(fn (Score $s) => [
                'criterionId' => $s->criterion_id,
                'name' => (string) $s->criterion?->name,
                'score' => $s->score,
                'comment' => $s->comment,
            ])->values()->all();
    }

    /**
     * @param Builder<Review> $query
     * @param array<string, mixed> $filters
     * @param array<string, string>|null $sort
     */
    private function listing(Builder $query, ?Pagination $pagination, Context $context, array $filters, ?array $sort): Context
    {
        foreach (['item' => 'item_id', 'user' => 'user_id', 'merchant' => 'merchant_id'] as $filter => $column) {
            if (isset($filters[$filter])) {
                $query->where('community_reviews.'.$column, (int) $filters[$filter]);
            }
        }
        if (($filters['status'] ?? null) === 'pending' && $context->getActor()->hasPermission('communityReviews.moderate')) {
            $query->where('community_reviews.status', Review::PENDING);
        }
        if (! empty($filters['photos'])) {
            $query->whereExists(fn (QueryBuilder $q) => $q->selectRaw('1')->from('community_review_photos')
                ->whereColumn('community_review_photos.review_id', 'community_reviews.id'));
        }
        if (isset($filters['item']) === false && isset($filters['user']) === false && isset($filters['merchant']) === false) {
            // The site-wide "latest reviews": only items the viewer may see (the scope already does this) and approved.
            $query->where('community_reviews.status', Review::APPROVED);
        }

        $default = $this->config->newestFirst() ? 'desc' : 'asc';
        foreach ($sort ?: ['createdAt' => $default] as $name => $direction) {
            $column = match ($name) {
                'helpful' => 'community_reviews.helpful_count',
                'rating' => 'community_reviews.rating',
                default => 'community_reviews.created_at',
            };
            $query->orderBy($column, $direction === 'desc' ? 'desc' : 'asc');
        }
        $query->orderBy('community_reviews.id', $sort ? 'desc' : $default);
        if ($pagination instanceof OffsetPagination) {
            $pagination->apply($query);
        }

        return $context->withQuery($query);
    }

    /** @return array{user: int, reviewCount: int, rating: float|null} */
    private function merchantSummary(Context $context): array
    {
        $filter = $context->request->getQueryParams()['filter'] ?? [];
        $user = (int) (is_array($filter) ? ($filter['merchant'] ?? 0) : 0);
        $query = Review::whereVisibleTo($context->getActor())->where('community_reviews.merchant_id', $user)->where('community_reviews.status', Review::APPROVED);
        $count = (clone $query)->count();

        return ['user' => $user, 'reviewCount' => $count, 'rating' => $count > 0 ? round((float) $query->avg('community_reviews.rating'), 2) : null];
    }

    private function vote(Context $context): ?Review
    {
        /** @var Review $review */
        $review = $context->model;
        $actor = $context->getActor();
        $helpful = (bool) ($context->body()['helpful'] ?? true);
        $votes = $this->db->table('community_review_votes');

        if ($helpful) {
            $votes->insertOrIgnore(['review_id' => $review->id, 'user_id' => $actor->id, 'created_at' => Carbon::now()]);
        } else {
            $votes->where('review_id', $review->id)->where('user_id', $actor->id)->delete();
        }
        Review::query()->whereKey($review->id)->update([
            'helpful_count' => $this->db->table('community_review_votes')->where('review_id', $review->id)->count(),
        ]);

        $fresh = $this->find((string) $review->id, $context);

        return $fresh instanceof Review ? $fresh : null;
    }
}
