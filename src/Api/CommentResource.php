<?php

declare(strict_types=1);

namespace ErnestDefoe\CommunityReviews\Api;

use ErnestDefoe\CommunityReviews\Config;
use ErnestDefoe\CommunityReviews\Model\Comment;
use ErnestDefoe\CommunityReviews\Model\Item;
use ErnestDefoe\CommunityReviews\Write\CommentWriter;
use Flarum\Api\Context;
use Flarum\Api\Endpoint;
use Flarum\Api\Resource\AbstractDatabaseResource;
use Flarum\Api\Schema;
use Flarum\Api\Sort\SortColumn;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Tobyz\JsonApiServer\Context as BaseContext;
use Tobyz\JsonApiServer\Pagination\OffsetPagination;
use Tobyz\JsonApiServer\Pagination\Pagination;

/**
 * The `review-comments` type (MyBB product comments).
 *
 * GET    /api/review-comments?filter[item]=N   in the admin's display order; filter[user]=N for a profile
 * POST   /api/review-comments   {body, reviewId?, isOfficial?, relationships.item}
 * PATCH  /api/review-comments/{id}  {body}      DELETE /api/review-comments/{id}
 *
 * @extends AbstractDatabaseResource<Comment>
 */
class CommentResource extends AbstractDatabaseResource
{
    use Concerns\ReadsInput;

    /** @var array<int, bool> */
    private array $isNew = [];

    public function __construct(
        private readonly Config $config,
        private readonly CommentWriter $writer,
    ) {
    }

    public function type(): string
    {
        return 'review-comments';
    }

    public function model(): string
    {
        return Comment::class;
    }

    /** @param Builder<Comment> $query */
    public function scope(Builder $query, BaseContext $context): void
    {
        $query->whereVisibleTo($context->getActor())->select('community_review_comments.*')->with(['user.groups', 'review.user', 'item']);
    }

    public function endpoints(): array
    {
        return [
            Endpoint\Index::make()
                ->defaultInclude(['user'])
                ->paginate(6, 50)
                ->query(fn (Builder $query, ?Pagination $pagination, Context $context, array $filters) => $this->listing($query, $pagination, $context, $filters)),
            Endpoint\Show::make()->defaultInclude(['user']),
            Endpoint\Create::make()->authenticated()->can('communityReviews.comment')->defaultInclude(['user']),
            Endpoint\Update::make()->authenticated()->can('edit')->defaultInclude(['user']),
            Endpoint\Delete::make()->authenticated()->can('delete'),
        ];
    }

    public function fields(): array
    {
        $can = fn (string $ability) => fn (Comment $c, Context $context): bool => $context->getActor()->can($ability, $c);

        return [
            Schema\Str::make('body')->writable()->set(fn () => null)
                ->visible(fn (Comment $c, Context $context): bool => $context->getActor()->can('edit', $c))
                ->get(fn (Comment $c): string => $c->bodySource()),
            Schema\Integer::make('reviewId')->property('review_id')->nullable()->writableOnCreate()->set(fn () => null),
            Schema\Boolean::make('isOfficial')->property('is_official')->writableOnCreate()->set(fn () => null),
            Schema\Str::make('bodyHtml')->get(fn (Comment $c, Context $context): string => $c->bodyHtml($context->request)),
            // "in reply to Jordan's review": the review's author name, from the eager-loaded review.
            Schema\Str::make('replyToName')->nullable()
                ->get(fn (Comment $c): ?string => $c->review !== null && $c->review->isApproved() ? $c->review->user?->display_name : null),
            Schema\Str::make('itemPath')->nullable()->get(fn (Comment $c): ?string => $c->item ? $c->item->id.'-'.$c->item->slug : null),
            Schema\Str::make('itemName')->nullable()->get(fn (Comment $c): ?string => $c->item?->name),
            Schema\DateTime::make('createdAt')->property('created_at'),
            Schema\DateTime::make('editedAt')->property('edited_at')->nullable(),
            Schema\Boolean::make('canEdit')->get($can('edit')),
            Schema\Boolean::make('canDelete')->get($can('delete')),
            Schema\Boolean::make('canReport')->get($can('report')),
            Schema\Relationship\ToOne::make('item')->type('review-items')->includable()->requiredOnCreate()->writableOnCreate(),
            Schema\Relationship\ToOne::make('user')->type('users')->includable(),
        ];
    }

    /** @return list<SortColumn> */
    public function sorts(): array
    {
        return [SortColumn::make('createdAt')];
    }

    /** @param Context $context */
    public function creating(object $model, BaseContext $context): ?object
    {
        $item = Item::whereVisibleTo($context->getActor())->find($model->item_id);
        $this->writer->prepare($model, $item, $context->getActor(), $this->attributes($context), $this->ip($context));
        $this->isNew[spl_object_id($model)] = true;

        return $model;
    }

    /** @param Context $context */
    public function updating(object $model, BaseContext $context): ?object
    {
        $this->writer->prepare($model, null, $context->getActor(), $this->attributes($context), null);

        return $model;
    }

    protected function saveModel(Model $model, BaseContext $context): void
    {
        parent::saveModel($model, $context);
        if ($model instanceof Comment) {
            $isNew = $this->isNew[spl_object_id($model)] ?? false;
            unset($this->isNew[spl_object_id($model)]);
            $this->writer->saved($model, $context->getActor(), $isNew);
        }
    }

    /** @param Context $context */
    public function deleting(object $model, BaseContext $context): void
    {
        if ($model instanceof Comment) {
            $this->writer->deleted($model);
        }
    }

    /**
     * @param Builder<Comment> $query
     * @param array<string, mixed> $filters
     */
    private function listing(Builder $query, ?Pagination $pagination, Context $context, array $filters): Context
    {
        if (isset($filters['user'])) {
            $query->where('community_review_comments.user_id', (int) $filters['user']);
        } else {
            $query->where('community_review_comments.item_id', (int) ($filters['item'] ?? 0));
        }

        $direction = $this->config->newestFirst() ? 'desc' : 'asc';
        $query->orderBy('community_review_comments.created_at', $direction)->orderBy('community_review_comments.id', $direction);
        if ($pagination instanceof OffsetPagination) {
            $pagination->apply($query);
        }

        return $context->withQuery($query);
    }
}
