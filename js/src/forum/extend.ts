import Extend from 'flarum/common/extenders';
import Category from './models/Category';
import Criterion from './models/Criterion';
import Item from './models/Item';
import Review from './models/Review';
import Comment from './models/Comment';
import Report from './models/Report';
import app from 'flarum/forum/app';
import Notification from 'flarum/forum/components/Notification';
import type Mithril from 'mithril';
import { t, ui } from './util';

/*
 * The declarative half of the forum: models and routes. The pages are one lazy chunk (./ui).
 */

export const NOTIFICATIONS: Record<string, string> = {
  communityReviewsMerchantTagged: 'fas fa-store',
  communityReviewsNewReview: 'fas fa-star-half-stroke',
  communityReviewsNewComment: 'far fa-comment',
};

/** MyBB's three MyAlerts as Flarum notifications: the subject is the review or comment, linked to its item page. */
function notificationComponent(type: string) {
  return class extends Notification {
    icon() {
      return NOTIFICATIONS[type];
    }
    href() {
      const subject = this.attrs.notification.subject() as Review | Comment | null;
      const path = subject?.itemPath?.();
      if (!path) return app.route('community-reviews.index');
      const anchor = subject instanceof Comment ? `#comment${subject.id()}` : `#review${subject?.id()}`;
      return app.route('community-reviews.item', { id: path }) + anchor;
    }
    content(): Mithril.Children {
      const subject = this.attrs.notification.subject() as Review | Comment | null;
      return t(`notifications.${type}`, { user: this.attrs.notification.fromUser(), item: subject?.itemName?.() || '' });
    }
    excerpt(): Mithril.Children {
      return null;
    }
  };
}

type PageName = 'CatalogPage' | 'ItemPage' | 'ReportsPage';
const page = (name: PageName) => () => ui().then(async (chunk) => (await chunk.loadCore(), { default: chunk[name] }));

export default [
  new Extend.Store()
    .add('review-categories', Category)
    .add('review-criteria', Criterion)
    .add('review-items', Item)
    .add('reviews', Review)
    .add('review-comments', Comment)
    .add('review-reports', Report),
  new Extend.Routes()
    .add('community-reviews.index', '/reviews', page('CatalogPage'))
    .add('community-reviews.category', '/reviews/c/:slug', page('CatalogPage'))
    .add('community-reviews.item', '/reviews/i/:id', page('ItemPage'))
    .add('community-reviews.reports', '/reviews/reports', page('ReportsPage'))
    .add('user.community-reviews', '/u/:username/reviews', () => ui().then(async (chunk) => ({ default: await chunk.userReviewsPage() }))),
  Object.keys(NOTIFICATIONS).reduce((ext, type) => ext.add(type, notificationComponent(type)), new Extend.Notification()),
];
