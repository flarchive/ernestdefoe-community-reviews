<?php

declare(strict_types=1);

namespace ErnestDefoe\CommunityReviews\Api;

use ErnestDefoe\CommunityReviews\Model\Category;
use ErnestDefoe\CommunityReviews\Model\Criterion;
use ErnestDefoe\CommunityReviews\Write\Clean;
use Flarum\Api\Context;
use Flarum\Api\Endpoint;
use Flarum\Api\Resource\AbstractDatabaseResource;
use Flarum\Api\Schema;
use Flarum\Foundation\ValidationException;
use Flarum\Locale\TranslatorInterface;
use Illuminate\Database\ConnectionInterface;
use Illuminate\Database\Eloquent\Builder;
use Laminas\Diactoros\Response\EmptyResponse;
use Tobyz\JsonApiServer\Context as BaseContext;

/**
 * The `review-criteria` type (MyBB "fields"). Read through the category; written by admins.
 *
 * POST   /api/review-criteria            {name, weight?, relationships.category}
 * PATCH  /api/review-criteria/{id}       DELETE /api/review-criteria/{id}
 * POST   /api/review-criteria/{id}/copy  {"categoryId": N}: MyBB's "Add to category…"
 * POST   /api/review-criteria/order      {"order": [ids…]}
 *
 * @extends AbstractDatabaseResource<Criterion>
 */
class CriterionResource extends AbstractDatabaseResource
{
    use Concerns\Reorders;

    public function __construct(
        private readonly ConnectionInterface $db,
        private readonly TranslatorInterface $translator,
    ) {
    }

    public function type(): string
    {
        return 'review-criteria';
    }

    public function model(): string
    {
        return Criterion::class;
    }

    /** @param Builder<Criterion> $query */
    public function scope(Builder $query, BaseContext $context): void
    {
        if (! $context->getActor()->hasPermission('communityReviews.view') && ! $context->getActor()->isAdmin()) {
            // Without the view permission the categories' criteria are hidden too.
            $query->whereRaw('1 = 0');
        }
    }

    public function endpoints(): array
    {
        return [
            Endpoint\Show::make(),
            Endpoint\Create::make()->authenticated()->admin(),
            Endpoint\Update::make()->authenticated()->admin(),
            Endpoint\Delete::make()->authenticated()->admin(),
            Endpoint\Endpoint::make('copy')
                ->route('POST', '/{id}/copy')
                ->authenticated()
                ->admin()
                ->action(fn (Context $context): Criterion => $this->copy($context)),
            Endpoint\Endpoint::make('order')
                ->route('POST', '/order')
                ->authenticated()
                ->admin()
                ->action(fn (Context $context) => $this->reorder(Criterion::query(), $this->db, Clean::ids($context->body()['order'] ?? [])))
                ->response(fn () => new EmptyResponse(204)),
        ];
    }

    public function fields(): array
    {
        return [
            Schema\Str::make('name')->requiredOnCreate()->writable()->minLength(1)->maxLength(100),
            Schema\Integer::make('weight')->writable()->min(1)->max(5),
            Schema\Integer::make('position'),
            Schema\Relationship\ToOne::make('category')->type('review-categories')->requiredOnCreate()->writableOnCreate(),
        ];
    }

    /** @param Context $context */
    public function creating(object $model, BaseContext $context): ?object
    {
        $model->weight = $model->weight ?: 1;
        $model->position = (int) Criterion::query()->where('category_id', $model->category_id)->max('position') + 1;

        return $model;
    }

    private function copy(Context $context): Criterion
    {
        /** @var Criterion $source */
        $source = $context->model;
        $target = Category::query()->find((int) ($context->body()['categoryId'] ?? 0));
        if ($target === null) {
            throw new ValidationException(['categoryId' => $this->translator->trans('ernestdefoe-community-reviews.api.category_missing')]);
        }

        $copy = new Criterion();
        $copy->category_id = $target->id;
        $copy->name = $source->name;
        $copy->weight = $source->weight;
        $copy->position = (int) Criterion::query()->where('category_id', $target->id)->max('position') + 1;
        $copy->save();

        return $copy;
    }
}
