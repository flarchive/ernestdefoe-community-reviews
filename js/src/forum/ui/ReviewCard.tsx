import app from 'flarum/forum/app';
import Component from 'flarum/common/Component';
import type { ComponentAttrs } from 'flarum/common/Component';
import Avatar from 'flarum/common/components/Avatar';
import Button from 'flarum/common/components/Button';
import Link from 'flarum/common/components/Link';
import humanTime from 'flarum/common/helpers/humanTime';
import username from 'flarum/common/helpers/username';
import type Mithril from 'mithril';
import type Item from '../models/Item';
import type Review from '../models/Review';
import { failed, setting, t, tx } from '../util';
import { scoreClass, scoreTile, stars, one } from './bits';
import { openComposer, openLightbox, openReport } from './open';
import Menu, { type MenuEntry } from './Menu';

export interface ReviewCardAttrs extends ComponentAttrs {
  review: Review;
  item: Item;
  onreply: (review: Review) => void;
  onchange: () => void;
}

/** One review, laid out like MyBB's: score, then every criterion's stars and comment, General thoughts, photos. */
export default class ReviewCard extends Component<ReviewCardAttrs> {
  view() {
    const { review } = this.attrs;
    return (
      <article className="CR-review" id={`review${review.id()}`}>
        {this.head()}
        <div className="CR-review-rate">
          {scoreTile(review.rating())}
          {review.title() ? <h3>{review.title()}</h3> : stars(review.rating())}
        </div>
        {this.props()}
        {this.criteria()}
        {review.bodyHtml() ? (
          <div className="CR-general">
            <h4>{String(setting('general_label') || '') || t('review.general')}</h4>
            <div className="CR-general-body">{m.trust(review.bodyHtml()!)}</div>
          </div>
        ) : null}
        {this.prosCons()}
        {this.photos()}
        {this.updated()}
        {this.footer()}
      </article>
    );
  }

  private head(): Mithril.Children {
    const { review } = this.attrs;
    const user = review.user();
    return (
      <header className="CR-review-head">
        <Avatar user={user || null} className="CR-review-avatar" />
        <div className="CR-review-who">
          <div className="CR-review-line1">
            {user ? <Link href={app.route.user(user)}>{username(user)}</Link> : username(null)}
            {review.isVerified() ? (
              <span className="CR-badge CR-badge--ok">
                <i className="fas fa-circle-check" aria-hidden="true" /> {t('review.verified')}
              </span>
            ) : null}
            {review.status() === 'pending' ? <span className="CR-badge CR-badge--warn">{t('review.pending')}</span> : null}
          </div>
          <div className="CR-review-line2">
            {review.ownedFor() ? `${tx(`owned.${review.ownedFor()}`)} · ` : ''}
            {humanTime(review.createdAt())} · <a href={`#review${review.id()}`}>#{review.id()}</a>
          </div>
        </div>
        {this.controls()}
      </header>
    );
  }

  private criteria(): Mithril.Children {
    return (
      <div className="CR-crits">
        {(this.attrs.review.scores() || []).map((s) => (
          <div className="CR-crit">
            <div className="CR-crit-head">
              <b>{s.name}</b>
              {stars(s.score)}
            </div>
            {s.comment ? <p>{s.comment}</p> : null}
          </div>
        ))}
      </div>
    );
  }

  private photos(): Mithril.Children {
    const photos = this.attrs.review.photos() || [];
    if (!photos.length) return null;
    return (
      <div className="CR-photos">
        {photos.map((photo, index) => (
          <button type="button" className="CR-photo" aria-label={tx('review.open_photo')} onclick={() => openLightbox(photos, index)}>
            <img src={photo.thumbUrl} alt="" loading="lazy" />
          </button>
        ))}
      </div>
    );
  }

  /** MyBB doesn't keep history; we show "Updated" with the score before the edit. */
  private updated(): Mithril.Children {
    const { review } = this.attrs;
    if (review.previousRating() === null || !review.editedAt()) return null;
    return (
      <div className="CR-updated">
        <i className="fas fa-clock-rotate-left" aria-hidden="true" /> {t('review.updated', { ago: humanTime(review.editedAt()!) })}{' '}
        <s>{review.previousRating()!.toFixed(1)}</s> → <span className={scoreClass(review.rating())}>{review.rating().toFixed(1)}</span>
      </div>
    );
  }

  private props(): Mithril.Children {
    const { review } = this.attrs;
    const merchant = review.merchant();
    let host = '';
    try {
      host = review.url() ? new URL(review.url()!).hostname : '';
    } catch {
      host = review.url() || '';
    }
    if (!review.price() && !review.url() && !merchant) return null;
    return (
      <div className="CR-props">
        {review.price() ? (
          <span>
            <i className="fas fa-tag" aria-hidden="true" /> {t('review.paid_label')} <b>{review.price()}</b>
          </span>
        ) : null}
        {review.url() ? (
          <span>
            <i className="fas fa-arrow-up-right-from-square" aria-hidden="true" />{' '}
            <a href={review.url()!} rel="nofollow ugc noopener" target="_blank">
              {host}
            </a>
          </span>
        ) : null}
        {merchant ? (
          <span>
            <i className="fas fa-store" aria-hidden="true" /> {t('review.bought_from')}{' '}
            <Link href={app.route('user.community-reviews', { username: merchant.slug() })}>{username(merchant)}</Link>{' '}
            <span className="CR-badge">{t('review.merchant')}</span>
          </span>
        ) : null}
      </div>
    );
  }

  private prosCons(): Mithril.Children {
    const { review } = this.attrs;
    const pros = review.pros() || [];
    const cons = review.cons() || [];
    if (!pros.length && !cons.length) return null;
    return (
      <div className="CR-procon">
        <ul className="CR-procon-pros">
          <li className="CR-procon-h">{t('review.pros')}</li>
          {pros.map((p) => (
            <li>
              <i className="fas fa-plus" aria-hidden="true" /> {p}
            </li>
          ))}
        </ul>
        <ul className="CR-procon-cons">
          <li className="CR-procon-h">{t('review.cons')}</li>
          {cons.map((c) => (
            <li>
              <i className="fas fa-minus" aria-hidden="true" /> {c}
            </li>
          ))}
        </ul>
      </div>
    );
  }

  private footer(): Mithril.Children {
    const { review, item } = this.attrs;
    const helpful = setting<boolean>('extra_helpful');
    return (
      <div className="CR-review-foot">
        {helpful ? (
          <button
            type="button"
            className={'CR-helpful' + (review.votedHelpful() ? ' is-on' : '')}
            disabled={!review.canVote()}
            aria-pressed={review.votedHelpful() ? 'true' : 'false'}
            onclick={() => this.vote()}
          >
            <i className={(review.votedHelpful() ? 'fas' : 'far') + ' fa-thumbs-up'} aria-hidden="true" />{' '}
            {t('review.helpful', { count: review.helpfulCount() || 0 })}
          </button>
        ) : null}
        {item.canComment() ? (
          <Button className="Button Button--link" icon="far fa-comment" onclick={() => this.attrs.onreply(review)}>
            {t('review.reply')}
          </Button>
        ) : null}
        {review.commentCount() ? <span className="CR-muted CR-review-replies">{t('review.replies', { count: review.commentCount() })}</span> : null}
      </div>
    );
  }

  private controls(): Mithril.Children {
    const { review, item } = this.attrs;
    const entries: MenuEntry[] = [];
    if (review.canEdit()) {
      entries.push({ icon: 'fas fa-pen', label: t('review.edit'), run: () => openComposer({ item, review, onsaved: this.attrs.onchange }) });
    }
    if (review.canModerate() && review.status() === 'pending') {
      entries.push({
        icon: 'fas fa-check',
        label: t('review.approve'),
        run: () => review.save({ status: 'approved' }).then(this.attrs.onchange).catch(failed),
      });
    }
    if (review.canModerate() && setting('extra_verified')) {
      const on = !!review.isVerified();
      entries.push({
        icon: on ? 'far fa-circle-xmark' : 'fas fa-circle-check',
        label: t(on ? 'review.unmark_verified' : 'review.mark_verified'),
        run: () =>
          review
            .save({ isVerified: !on })
            .then(() => m.redraw())
            .catch(failed),
      });
    }
    if (review.canDelete()) {
      entries.push({
        icon: 'far fa-trash-can',
        label: t('review.delete'),
        run: () => {
          if (confirm(tx('review.delete_confirm'))) review.delete().then(this.attrs.onchange).catch(failed);
        },
      });
    }
    if (review.canReport() && app.session.user?.id() !== one(review.user())?.id()) {
      entries.push({ icon: 'far fa-flag', label: t('report.action'), run: () => openReport('review', Number(review.id())) });
    }

    return <Menu entries={entries} label={tx('review.controls')} />;
  }

  private vote(): void {
    const { review } = this.attrs;
    app
      .request({ method: 'POST', url: `${app.forum.attribute('apiUrl')}/reviews/${review.id()}/helpful`, body: { helpful: !review.votedHelpful() } })
      .then((payload) => {
        app.store.pushPayload(payload as never);
        m.redraw();
      })
      .catch(failed);
  }
}
