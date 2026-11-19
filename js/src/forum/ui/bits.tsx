import app from 'flarum/forum/app';
import classList from 'flarum/common/utils/classList';
import type Mithril from 'mithril';
import type Item from '../models/Item';

/** A has-one relationship as the model or null (Flarum returns false when it isn't loaded). */
export function one<T>(value: T | false | undefined | null): T | null {
  return value || null;
}

/** 1-5 stars, the off ones drawn muted. `value` may be fractional; it is rounded to the nearest star. */
export function stars(value: number | null | undefined, className = ''): Mithril.Children {
  const n = Math.max(0, Math.min(5, Math.round(value || 0)));
  return (
    <span className={classList('CR-stars', className)} aria-label={`${n} / 5`} role="img">
      {'★'.repeat(n)}
      <span className="CR-stars-off">{'★'.repeat(5 - n)}</span>
    </span>
  );
}

/** great ≥ 4.5, good ≥ 3.5, mid below; no rating yet = new. */
export function scoreClass(rating: number | null | undefined, ranked = true): string {
  if (rating === null || rating === undefined || !ranked) return 'is-new';
  if (rating >= 4.5) return 'is-great';
  if (rating >= 3.5) return 'is-good';
  return 'is-mid';
}

export function scoreTile(rating: number | null | undefined, className = '', ranked = true): Mithril.Children {
  return (
    <span className={classList('CR-score', scoreClass(rating, ranked), className)}>
      {rating === null || rating === undefined ? '–' : rating.toFixed(1)}
    </span>
  );
}

/** An item's picture: its thumbnail, or a placeholder in the category's colour. */
export function art(item: Item, full = false): Mithril.Children {
  const url = full ? item.photoUrl() || item.thumbnailUrl() : item.thumbnailUrl();
  const color = one(item.category())?.color?.() || '';
  return (
    <span className={classList('CR-art', !url && 'CR-art--empty')} style={!url && color ? { '--cr-tint': color } : undefined}>
      {url ? (
        <img src={url} alt="" loading="lazy" />
      ) : (
        <i className={classList(one(item.category())?.icon?.() || 'fas fa-box-open')} aria-hidden="true" />
      )}
    </span>
  );
}

/** A short count: 980, 2.4k, 96k. */
export function count(n: number): string {
  if (n < 1000) return String(n);
  if (n < 10000) return (n / 1000).toFixed(1).replace(/\.0$/, '') + 'k';
  return Math.round(n / 1000) + 'k';
}

/** Whether an item has enough reviews to be ranked (the admin's threshold). */
export function ranked(item: Item): boolean {
  const all = app.forum.attribute<Record<string, unknown>>('communityReviews') || {};
  return item.reviewCount() >= Number(all.rank_min_reviews || 1);
}
