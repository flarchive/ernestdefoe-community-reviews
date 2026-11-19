import app from 'flarum/forum/app';

export const EXT = 'ernestdefoe-community-reviews';

/** A forum string from our locale. */
export const t = (key: string, params: Record<string, unknown> = {}) => app.translator.trans(`${EXT}.forum.${key}`, params);

/** The same, as plain text (for attributes, titles and aria labels). */
export const tx = (key: string, params: Record<string, unknown> = {}): string =>
  app.translator.trans(`${EXT}.forum.${key}`, params, true) as unknown as string;

/** The admin's settings, from the forum payload (Api\ForumFields). */
export function setting<T = unknown>(key: string): T {
  const all = app.forum.attribute<Record<string, unknown>>('communityReviews') || {};
  return all[key] as T;
}

/** A request that didn't come back: say so, in words, instead of failing silently. */
export function failed(): void {
  app.alerts.show({ type: 'error' }, tx('load_failed'));
}

/** The pages, the composer and the modals: one lazy chunk, fetched on first use. */
export const ui = () => import('./ui');
