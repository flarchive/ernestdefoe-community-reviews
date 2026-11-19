<?php

declare(strict_types=1);

namespace ErnestDefoe\CommunityReviews;

use Flarum\Settings\SettingsRepositoryInterface;

/**
 * Every setting, typed. Defaults are registered once in extend.php (Extend\Settings()->default()), so
 * nothing here repeats them. Out of the box the extension behaves like MyBB's Community Reviews:
 * every extra is off.
 */
class Config
{
    public const PREFIX = 'ernestdefoe-community-reviews.';

    public const ITEM_ORDERS = ['name', 'rating', 'reviews', 'views', 'date'];

    public function __construct(private readonly SettingsRepositoryInterface $settings)
    {
    }

    public function get(string $key): mixed
    {
        return $this->settings->get(self::PREFIX.$key);
    }

    public function int(string $key): int
    {
        return (int) $this->get($key);
    }

    public function bool(string $key): bool
    {
        return (bool) $this->get($key);
    }

    public function string(string $key): string
    {
        return trim((string) $this->get($key));
    }

    public function itemOrder(): string
    {
        $order = $this->string('item_order');

        return in_array($order, self::ITEM_ORDERS, true) ? $order : 'name';
    }

    public function itemOrderDescending(): bool
    {
        return $this->string('item_order_dir') === 'desc';
    }

    public function newestFirst(): bool
    {
        return $this->string('display_order') !== 'asc';
    }

    public function maxPhotos(): int
    {
        return max(0, min(20, $this->int('max_photos')));
    }

    public function merchantGroupId(): ?int
    {
        $id = $this->int('merchant_group');

        return $id > 0 ? $id : null;
    }

    public function imgur(): bool
    {
        return $this->string('photo_host') === 'imgur' && $this->string('imgur_client_id') !== '';
    }

    /** An extra (helpful, verified, pros_cons, rich_results): all off by default, like MyBB. */
    public function extra(string $name): bool
    {
        return $this->bool('extra_'.$name);
    }
}
