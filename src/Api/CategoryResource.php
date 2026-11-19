<?php

declare(strict_types=1);

namespace ErnestDefoe\CommunityReviews\Api;

use Carbon\Carbon;
use ErnestDefoe\CommunityReviews\Model\Category;
use ErnestDefoe\CommunityReviews\Write\Clean;
use Flarum\Api\Context;
use Flarum\Api\Endpoint;
use Flarum\Api\Resource\AbstractDatabaseResource;
use Flarum\Api\Schema;
use Flarum\Api\Sort\SortColumn;
use Illuminate\Database\ConnectionInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Str;
use Laminas\Diactoros\Response\EmptyResponse;
use Tobyz\JsonApiServer\Context as BaseContext;

/**
 * The `review-categories` type (MyBB "categories"), each with its criteria (MyBB "fields").
 *
 * GET    /api/review-categories            everyone who may view reviews; in the admin's order, criteria included
 * POST   /api/review-categories            admins          PATCH/DELETE /api/review-categories/{id}   admins
 * POST   /api/review-categories/order      admins: {"order": [ids…]} (one CASE update)
 *
 * @extends AbstractDatabaseResource<Category>
 */
class CategoryResource extends AbstractDatabaseResource
{
    use Concerns\Reorders;

    public function __construct(private readonly ConnectionInterface $db)
    {
    }

    public function type(): string
    {
        return 'review-categories';
    }

    public function model(): string
    {
        return Category::class;
    }

    /** @param Builder<Category> $query */
    public function scope(Builder $query, BaseContext $context): void
    {
        if (! $context->getActor()->hasPermission('communityReviews.view') && ! $context->getActor()->isAdmin()) {
            // Without the view permission the catalog's structure is hidden too (an empty list, not an error).
            $query->whereRaw('1 = 0');
        }
    }

    public function endpoints(): array
    {
        return [
            Endpoint\Index::make()
                ->defaultInclude(['criteria'])
                ->defaultSort('position'),
            Endpoint\Show::make()->defaultInclude(['criteria']),
            Endpoint\Create::make()->authenticated()->admin(),
            Endpoint\Update::make()->authenticated()->admin(),
            Endpoint\Delete::make()->authenticated()->admin(),
            Endpoint\Endpoint::make('order')
                ->route('POST', '/order')
                ->authenticated()
                ->admin()
                ->action(fn (Context $context) => $this->reorder(Category::query(), $this->db, Clean::ids($context->body()['order'] ?? [])))
                ->response(fn () => new EmptyResponse(204)),
        ];
    }

    public function fields(): array
    {
        return [
            Schema\Str::make('name')->requiredOnCreate()->writable()->minLength(1)->maxLength(100)
                ->set(function (Category $category, string $value): void {
                    $category->name = trim($value);
                    if (! $category->exists || $category->isDirty('name')) {
                        $category->slug = $this->slug($value, $category->id);
                    }
                }),
            Schema\Str::make('slug'),
            Schema\Str::make('description')->nullable()->writable()->maxLength(500),
            Schema\Str::make('icon')->nullable()->writable()->maxLength(64),
            Schema\Str::make('color')->nullable()->writable()->maxLength(16)->regex('/^#[0-9a-fA-F]{3,8}$/'),
            Schema\Integer::make('position'),
            Schema\Integer::make('parentId')->property('parent_id')->nullable()->writable()
                ->set(fn (Category $category, ?int $value) => $category->parent_id = $value !== null && $value !== $category->id
                    && Category::query()->whereKey($value)->whereNull('parent_id')->exists() ? $value : null),
            Schema\Arr::make('itemFields')->property('item_fields')->nullable()->writable()
                ->set(fn (Category $category, ?array $value) => $category->item_fields = $this->itemFields($value)),
            Schema\Integer::make('itemCount')->property('item_count'),
            Schema\Integer::make('reviewCount')->property('review_count'),
            Schema\Relationship\ToMany::make('criteria')->type('review-criteria')->includable(),
        ];
    }

    /** @return list<SortColumn> */
    public function sorts(): array
    {
        return [SortColumn::make('position')];
    }

    /** @param Context $context */
    public function creating(object $model, BaseContext $context): ?object
    {
        $model->position = (int) Category::query()->max('position') + 1;
        $model->created_at = Carbon::now();

        return $model;
    }

    private function slug(string $name, ?int $id): string
    {
        $base = Str::slug($name) ?: 'category';
        $slug = $base;
        for ($n = 2; Category::query()->where('slug', $slug)->when($id, fn ($q) => $q->whereKeyNot($id))->exists(); $n++) {
            $slug = $base.'-'.$n;
        }

        return $slug;
    }

    /**
     * @param array<mixed>|null $value
     * @return list<array{key: string, label: string, type: string, options: list<string>, filter: bool}>|null
     */
    private function itemFields(?array $value): ?array
    {
        $out = [];
        foreach ($value ?? [] as $field) {
            $label = is_array($field) ? Clean::text($field['label'] ?? null) : null;
            if ($label === null) {
                continue;
            }
            $type = in_array($field['type'] ?? '', ['text', 'number', 'choice'], true) ? $field['type'] : 'text';
            $out[] = [
                'key' => Str::slug((string) ($field['key'] ?? $label), '_') ?: 'f'.count($out),
                'label' => mb_substr($label, 0, 60),
                'type' => $type,
                'options' => $type === 'choice' ? Clean::labels($field['options'] ?? [], 30, 60) : [],
                'filter' => (bool) ($field['filter'] ?? false),
            ];
        }

        return $out === [] ? null : array_slice($out, 0, 12);
    }
}
