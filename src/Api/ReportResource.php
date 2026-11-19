<?php

declare(strict_types=1);

namespace ErnestDefoe\CommunityReviews\Api;

use Carbon\Carbon;
use ErnestDefoe\CommunityReviews\Model\Comment;
use ErnestDefoe\CommunityReviews\Model\Item;
use ErnestDefoe\CommunityReviews\Model\Report;
use ErnestDefoe\CommunityReviews\Model\Review;
use ErnestDefoe\CommunityReviews\Write\Fail;
use Flarum\Api\Context;
use Flarum\Api\Endpoint;
use Flarum\Api\Resource\AbstractDatabaseResource;
use Flarum\Api\Schema;
use Flarum\Api\Sort\SortColumn;
use Illuminate\Database\ConnectionInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Str;
use Tobyz\JsonApiServer\Context as BaseContext;
use Tobyz\JsonApiServer\Pagination\OffsetPagination;

/**
 * The `review-reports` type: MyBB's Report link for products, reviews and comments, in our own queue
 * (flarum/flags only knows posts).
 *
 * POST   /api/review-reports        {subjectType: item|review|comment, subjectId, reason?}: any member who can see it
 * GET    /api/review-reports        moderators: open reports, newest first, with item and reporter
 * PATCH  /api/review-reports/{id}   moderators: {"resolved": true}
 *
 * @extends AbstractDatabaseResource<Report>
 */
class ReportResource extends AbstractDatabaseResource
{
    public function __construct(
        private readonly Fail $fail,
        private readonly ConnectionInterface $db,
    ) {
    }

    public function type(): string
    {
        return 'review-reports';
    }

    public function model(): string
    {
        return Report::class;
    }

    /** @param Builder<Report> $query */
    public function scope(Builder $query, BaseContext $context): void
    {
        $actor = $context->getActor();
        if (! $actor->hasPermission('communityReviews.moderate')) {
            $query->where('community_review_reports.user_id', $actor->isGuest() ? 0 : $actor->id);
        }
    }

    public function endpoints(): array
    {
        return [
            Endpoint\Index::make()
                ->authenticated()
                ->can('communityReviews.moderate')
                ->defaultInclude(['item', 'user'])
                ->defaultSort('-createdAt')
                ->paginate(20, 50)
                ->query(function (Builder $query, $pagination, Context $context) {
                    $query->whereNull('community_review_reports.resolved_at')->orderByDesc('community_review_reports.created_at');
                    if ($pagination instanceof OffsetPagination) {
            $pagination->apply($query);
        }

                    return $context->withQuery($query);
                }),
            Endpoint\Create::make()->authenticated(),
            Endpoint\Update::make()->authenticated()->can('communityReviews.moderate'),
        ];
    }

    public function fields(): array
    {
        return [
            Schema\Str::make('subjectType')->property('kind')->requiredOnCreate()->writableOnCreate()->in(Report::SUBJECTS),
            Schema\Integer::make('subjectId')->property('target_id')->requiredOnCreate()->writableOnCreate(),
            Schema\Str::make('reason')->nullable()->writableOnCreate()->maxLength(500),
            Schema\Str::make('excerpt')->nullable(),
            Schema\DateTime::make('createdAt')->property('created_at'),
            Schema\Boolean::make('resolved')->writableOnUpdate()
                ->get(fn (Report $r): bool => $r->resolved_at !== null)
                ->set(function (Report $r, bool $value, Context $context): void {
                    $r->resolved_at = $value ? Carbon::now() : null;
                    $r->resolved_by = $value ? $context->getActor()->id : null;
                }),
            Schema\Relationship\ToOne::make('item')->type('review-items')->includable(),
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
        $actor = $context->getActor();
        $subject = match ($model->kind) {
            'item' => Item::whereVisibleTo($actor)->find($model->target_id),
            'review' => Review::whereVisibleTo($actor)->find($model->target_id),
            default => Comment::whereVisibleTo($actor)->find($model->target_id),
        };
        // Something gone or out of sight answers like something that never existed (no probing).
        if ($subject === null) {
            throw new ModelNotFoundException();
        }
        $actor->assertCan('report', $subject);
        if (Report::query()->where('kind', $model->kind)->where('target_id', $model->target_id)->where('user_id', $actor->id)->exists()) {
            $this->fail->on('reason', 'already_reported');
        }

        $model->item_id = $subject instanceof Item ? $subject->id : $subject->item_id;
        $model->excerpt = Str::limit(trim(strip_tags(match (true) {
            $subject instanceof Item => $subject->name,
            $subject instanceof Review => trim(($subject->title ?? '').' '.($subject->bodySource() ?? '')),
            default => $subject->bodySource(),
        })), 250);
        $model->user_id = $actor->id;
        $model->created_at = Carbon::now();

        return $model;
    }

    /**
     * A second report racing the first hits the unique key: the same translated answer as the check above. The save
     * runs in its own transaction (a savepoint inside the request's), because PostgreSQL aborts the whole outer
     * transaction on a caught constraint violation.
     */
    protected function saveModel(Model $model, BaseContext $context): void
    {
        try {
            $this->db->transaction(fn () => parent::saveModel($model, $context));
        } catch (UniqueConstraintViolationException) {
            $this->fail->on('reason', 'already_reported');
        }
    }
}
