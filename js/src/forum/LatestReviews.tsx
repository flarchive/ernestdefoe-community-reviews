import app from 'flarum/forum/app';
import Component from 'flarum/common/Component';
import Link from 'flarum/common/components/Link';
import humanTime from 'flarum/common/helpers/humanTime';
import username from 'flarum/common/helpers/username';
import type Mithril from 'mithril';
import type Review from './models/Review';
import { setting, t } from './util';

/** The index widget: the newest reviews, compact (the admin's widget length), in forum.js so it needs no chunk. */
export default class LatestReviews extends Component {
  private reviews: Review[] = [];

  oninit(vnode: Mithril.Vnode) {
    super.oninit(vnode);
    app.store
      .find<Review[]>('reviews', { include: 'user', page: { limit: Number(setting('widget_limit')), total: 0 } })
      .then((list) => (this.reviews = [...list]))
      .catch(() => {
        /* the widget simply stays empty; the discussion list above it is what the page is for */
      })
      .finally(() => m.redraw());
  }

  view() {
    if (!this.reviews.length) return null;
    return (
      <section className="CR-widget">
        <header className="CR-widget-head">
          <h3>{t('widget.title')}</h3>
          <Link href={app.route('community-reviews.index')}>{t('widget.all')}</Link>
        </header>
        <ul>
          {this.reviews.map((review) => (
            <li>
              <Link href={app.route('community-reviews.item', { id: review.itemPath() || '' }) + `#review${review.id()}`}>
                <span className="CR-widget-score">{review.rating().toFixed(1)}</span>
                <span className="CR-widget-name">{review.itemName()}</span>
                <span className="CR-muted">
                  {username(review.user() || null)} · {humanTime(review.createdAt())}
                </span>
              </Link>
            </li>
          ))}
        </ul>
      </section>
    );
  }
}
