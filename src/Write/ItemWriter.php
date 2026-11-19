<?php

declare(strict_types=1);

namespace ErnestDefoe\CommunityReviews\Write;

use ErnestDefoe\CommunityReviews\Config;
use ErnestDefoe\CommunityReviews\Job\RefreshItem;
use ErnestDefoe\CommunityReviews\Model\Category;
use ErnestDefoe\CommunityReviews\Model\Item;
use Flarum\User\User;
use Illuminate\Contracts\Queue\Queue;
use Illuminate\Database\ConnectionInterface;
use Illuminate\Support\Str;

/**
 * Adding and editing an item (MyBB "product"): a category, a unique name within the admin's length limit, and,
 * as extras, a brand and the category's spec fields. MyBB adds a product together with its first review: the
 * item resource does both in one request (ItemResource::creating) through this writer and ReviewWriter.
 */
class ItemWriter
{
    public function __construct(
        private readonly Config $config,
        private readonly Fail $fail,
        private readonly ConnectionInterface $db,
        private readonly Queue $queue,
    ) {
    }

    /** @param array<string, mixed> $input */
    public function prepare(Item $item, User $actor, array $input): void
    {
        $isNew = ! $item->exists;
        if ($isNew) {
            $actor->assertRegistered();
            $actor->assertPermission($actor->hasPermission('communityReviews.addItems'));
            $item->user_id = $actor->id;
            $item->status = $this->config->bool('item_approval') && ! $actor->hasPermission('communityReviews.skipApproval')
                ? Item::PENDING : Item::APPROVED;
        } else {
            $actor->assertCan('edit', $item);
        }

        if ($isNew || array_key_exists('categoryId', $input)) {
            $category = Category::query()->find((int) ($input['categoryId'] ?? 0));
            if ($category === null) {
                $this->fail->on('categoryId', 'category_missing');
            }
            $item->category_id = $category->id;
        }

        if ($isNew || array_key_exists('name', $input)) {
            $this->name($item, Clean::text($input['name'] ?? null));
        }

        if (array_key_exists('brand', $input)) {
            $brand = Clean::text($input['brand']);
            $item->brand = $brand === null ? null : mb_substr($brand, 0, 100);
        }
        if (array_key_exists('fields', $input)) {
            $item->fields = $this->fields($item, $input['fields']);
        }
        if (array_key_exists('status', $input) && $item->exists && $actor->hasPermission('communityReviews.moderate')
            && in_array($input['status'], [Item::APPROVED, Item::PENDING], true)) {
            $item->status = $input['status'];
        }
    }

    public function saved(Item $item): void
    {
        $this->db->afterCommit(fn () => $this->queue->push(new RefreshItem($item->id)));
    }

    public function deleted(Item $item): void
    {
        $category = $item->category_id;
        $this->db->afterCommit(fn () => $this->queue->push(new \ErnestDefoe\CommunityReviews\Job\RefreshCategory($category)));
    }

    private function name(Item $item, ?string $name): void
    {
        $max = max(1, $this->config->int('name_max'));
        if ($name === null) {
            $this->fail->on('name', 'name_missing');
        }
        if (mb_strlen($name) > $max) {
            $this->fail->on('name', 'name_too_long', ['max' => $max]);
        }

        // MyBB: "The specified product already exists." Same name, any case, anywhere in the catalog.
        // LOWER() on both sides: case-insensitive on every driver (PostgreSQL compares case-sensitively).
        $taken = Item::query()
            ->whereRaw('LOWER('.$this->db->getQueryGrammar()->wrap('community_review_items.name').') = ?', [mb_strtolower($name)])
            ->when($item->exists, fn ($q) => $q->whereKeyNot($item->id))
            ->exists();
        if ($taken) {
            $this->fail->on('name', 'name_taken');
        }

        $item->name = $name;
        $item->slug = Str::slug($name) ?: 'item';
    }

    /** @return array<string, string>|null only the category's own fields, each a short string */
    private function fields(Item $item, mixed $value): ?array
    {
        $defined = Category::query()->whereKey($item->category_id)->value('item_fields');
        $keys = [];
        foreach (is_string($defined) ? (json_decode($defined, true) ?: []) : [] as $field) {
            if (is_array($field) && isset($field['key'])) {
                $keys[] = (string) $field['key'];
            }
        }

        $out = [];
        foreach (is_array($value) ? $value : [] as $key => $v) {
            $v = Clean::text($v);
            if (in_array((string) $key, $keys, true) && $v !== null) {
                $out[(string) $key] = mb_substr($v, 0, 100);
            }
        }

        return $out === [] ? null : $out;
    }
}
