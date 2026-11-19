import app from 'flarum/forum/app';
import Link from 'flarum/common/components/Link';
import humanTime from 'flarum/common/helpers/humanTime';
import username from 'flarum/common/helpers/username';
import type Mithril from 'mithril';
import type Item from '../models/Item';
import type Review from '../models/Review';
import { art, count, ranked, scoreTile, stars } from './bits';
import { setting, t, tx } from '../util';

/** The name, cut to the admin's card length (MyBB product_name_length_card); the full name stays in the title. */
function shortName(name: string): string {
  const max = Number(setting('card_name_max'));
  return name.length > max ? name.slice(0, max).trimEnd() + '…' : name;
}

/** A product card: picture, score tile, brand, name, stars, counts. `rankNo` adds the #1/#2/#3 medal. */
export function itemCard(item: Item, rankNo?: number): Mithril.Children {
  const href = app.route('community-reviews.item', { id: item.urlId() });
  const isRanked = ranked(item);
  return (
    <article className="CR-card" key={item.id()}>
      <Link className="CR-card-art" href={href} aria-label={item.name()}>
        {art(item)}
        {rankNo && rankNo <= 3 && isRanked ? (
          <span className="CR-medal">
            <i className="fas fa-trophy" aria-hidden="true" /> #{rankNo}
          </span>
        ) : null}
        {item.status() === 'pending' ? <span className="CR-medal CR-medal--pending">{t('item.pending')}</span> : null}
        {scoreTile(item.rating(), 'CR-card-score', item.rating() !== null)}
      </Link>
      <div className="CR-card-body">
        {item.brand() ? <div className="CR-card-brand">{item.brand()}</div> : null}
        <h3 className="CR-card-name" title={item.name()}>
          <Link href={href}>{shortName(item.name())}</Link>
        </h3>
        <div className="CR-card-rate">
          {item.rating() !== null ? stars(item.rating()) : null}
          <span className="CR-muted">
            {t('card.counts', { reviews: item.reviewCount(), comments: item.commentCount(), views: count(item.viewCount()) })}
            {item.rating() !== null && !isRanked ? ` · ${tx('card.not_ranked')}` : ''}
          </span>
        </div>
      </div>
    </article>
  );
}

/** A review in a list (latest reviews, a profile): the item's picture, its name, the score and a line of text. */
export function reviewRow(review: Review): Mithril.Children {
  const item = review.item();
  if (!item) return null;
  const user = review.user();
  const quote = review.title() || review.scores()?.[0]?.comment || '';
  return (
    <Link className="CR-row" key={review.id()} href={app.route('community-reviews.item', { id: item.urlId() }) + `?review=${review.id()}`}>
      {art(item)}
      <div className="CR-row-main">
        <div className="CR-row-top">
          <b>{item.name()}</b>
          {scoreTile(review.rating(), 'CR-score--sm')}
        </div>
        {quote ? <p className="CR-row-quote">“{quote}”</p> : null}
        <span className="CR-muted">
          {username(user || null)}
          {review.price() ? ` · ${tx('review.paid', { price: review.price() })}` : ''} · {humanTime(review.createdAt())}
        </span>
      </div>
    </Link>
  );
}
