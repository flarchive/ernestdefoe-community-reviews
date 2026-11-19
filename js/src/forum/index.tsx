import app from 'flarum/forum/app';
import { extend } from 'flarum/common/extend';
import Button from 'flarum/common/components/Button';
import LinkButton from 'flarum/common/components/LinkButton';
import Separator from 'flarum/common/components/Separator';
import type ItemList from 'flarum/common/utils/ItemList';
import type UserPage from 'flarum/forum/components/UserPage';
import type Mithril from 'mithril';
import type Category from './models/Category';
import extenders, { NOTIFICATIONS } from './extend';
import LatestReviews from './LatestReviews';
import { setting, t, ui } from './util';

export { extenders as extend };

export { default as Item } from './models/Item';
export { default as Review } from './models/Review';

/*
 * forum.js carries the models, the routes and the navigation. The pages, the composer and the modals are one
 * lazy chunk (./ui), fetched the first time someone opens Reviews.
 */

function routeName(): string {
  return (app.current.get('routeName') as string | undefined) || '';
}

/** Whether the current page is one of ours (the sidebar then lists the categories). */
function onReviewsPage(): boolean {
  return routeName().startsWith('community-reviews.');
}

/** On review pages the sidebar's main button adds a product, the way IndexPage's starts a discussion. */
function addProductButton(items: ItemList<Mithril.Children>): void {
  if (!onReviewsPage() || !items.has('newDiscussion')) return;
  items.remove('newDiscussion');
  if (!app.forum.attribute('communityReviewsCanAddItems')) return;
  items.add(
    'communityReviewsAdd',
    <Button
      className="Button Button--primary IndexPage-newDiscussion"
      icon="fas fa-plus"
      onclick={() => ui().then((chunk) => chunk.openAddItem(null))}
    >
      {t('page.add_item')}
    </Button>,
    100
  );
}

function reviewsLink(items: ItemList<Mithril.Children>): void {
  const name = routeName();
  items.add(
    'communityReviews',
    <LinkButton
      href={app.route('community-reviews.index')}
      icon="fas fa-star-half-stroke"
      active={onReviewsPage() && !name.startsWith('community-reviews.category') && name !== 'community-reviews.reports'}
    >
      {t('nav.reviews')}
    </LinkButton>,
    75
  );
}

/** The moderators' reports link. Its count is drawn by CSS from an attribute: SelectDropdown repeats the active label. */
function reportsLink(items: ItemList<Mithril.Children>): void {
  if (!app.forum.attribute('communityReviewsCanModerate')) return;
  const open = Number(app.forum.attribute('communityReviewsOpenReports') || 0);
  items.add(
    'communityReviewsReports',
    <LinkButton
      href={app.route('community-reviews.reports')}
      icon="fas fa-flag"
      className="CommunityReviewsNav-reports"
      data-count={open ? (open > 99 ? '99+' : String(open)) : undefined}
    >
      {t('nav.reports')}
    </LinkButton>,
    74
  );
}

/** On review pages: the categories, children indented under their parent (MyBB's category listing). */
function categoryLinks(items: ItemList<Mithril.Children>): void {
  if (!onReviewsPage()) return;
  const categories = app.store.all<Category>('review-categories').sort((a, b) => a.position() - b.position());
  if (!categories.length) return;
  items.add('communityReviewsSeparator', <Separator />, 60);
  categories
    .filter((c) => !c.parentId())
    .forEach((category, index) => {
      const link = (c: Category, child: boolean) => (
        <LinkButton
          href={app.route('community-reviews.category', { slug: c.slug() })}
          icon={child ? undefined : c.icon() || 'fas fa-folder'}
          className={child ? 'CommunityReviewsNav-child' : 'CommunityReviewsNav-category'}
        >
          {c.name()}
        </LinkButton>
      );
      items.add(`communityReviewsCategory${category.id()}`, link(category, false), 50 - index * 2);
      categories
        .filter((c) => c.parentId() === Number(category.id()))
        .forEach((child, i) => items.add(`communityReviewsCategory${child.id()}`, link(child, true), 49 - index * 2 - i * 0.01));
    });
}

app.initializers.add('ernestdefoe-community-reviews', () => {
  extend('flarum/forum/components/IndexSidebar', 'items', addProductButton);

  extend('flarum/forum/components/IndexSidebar', 'navItems', function (items: ItemList<Mithril.Children>) {
    if (!app.forum.attribute('communityReviewsCanView')) return;
    reviewsLink(items);
    reportsLink(items);
    categoryLinks(items);
  });

  // MyBB's index widget ({$community_reviews_widget} at index_end): the latest reviews under the discussion list.
  extend('flarum/forum/components/IndexPage', 'contentItems', function (items: ItemList<Mithril.Children>) {
    if (setting('index_widget') && app.forum.attribute('communityReviewsCanView')) items.add('communityReviews', <LatestReviews />, -100);
  });

  extend(
    'flarum/forum/components/NotificationGrid',
    'notificationTypes',
    function (items: ItemList<{ name: string; icon: string; label: Mithril.Children }>) {
      Object.entries(NOTIFICATIONS).forEach(([name, icon]) => items.add(name, { name, icon, label: t(`notifications.${name}_preference`) }));
    }
  );

  extend('flarum/forum/components/UserPage', 'navItems', function (this: UserPage, items: ItemList<Mithril.Children>) {
    if (!this.user || !app.forum.attribute('communityReviewsCanView')) return;
    items.add(
      'communityReviews',
      <LinkButton href={app.route('user.community-reviews', { username: this.user.slug() })} icon="fas fa-star-half-stroke">
        {t('nav.reviews')}
      </LinkButton>,
      84
    );
  });
});
