import app from 'flarum/forum/app';
import Page from 'flarum/common/components/Page';
import Button from 'flarum/common/components/Button';
import LoadingIndicator from 'flarum/common/components/LoadingIndicator';
import Link from 'flarum/common/components/Link';
import username from 'flarum/common/helpers/username';
import classList from 'flarum/common/utils/classList';
import type Mithril from 'mithril';
import type Item from '../models/Item';
import type Review from '../models/Review';
import { failed, setting, t, tx } from '../util';
import { IndexSidebar, PageStructure } from './core';
import { art, count, ranked, scoreTile, stars, one } from './bits';
import ReviewCard from './ReviewCard';
import Comments from './Comments';
import Menu, { type MenuEntry } from './Menu';
import { openComposer, openMerge, openReport } from './open';

type ReviewSort = 'createdAt' | 'helpful' | 'rating';

/**
 * `/reviews/i/:id`: the item (MyBB "Product summary"), its reviews (MyBB "Product reviews", in the admin's order)
 * and its comments (MyBB "Product comments"). Opening it counts a view.
 */
export default class ItemPage extends Page {
  private item: Item | null = null;
  private reviews: Review[] = [];
  private loading = true;
  private loadingReviews = true;
  private moreReviews = false;
  private sort: ReviewSort = 'createdAt';
  private newestFirst = true;
  private withPhotos = false;
  private replyTo: Review | null = null;

  oninit(vnode: Mithril.Vnode) {
    super.oninit(vnode);
    this.bodyClass = 'CommunityReviewsPage-body';
    this.newestFirst = setting('display_order') !== 'asc';
    this.load();
    // The sidebar lists every category (IndexSidebar reads them from the store).
    app.store
      .find('review-categories')
      .then(() => m.redraw())
      .catch(failed);
  }

  private load(): void {
    const id = String(m.route.param('id')).split('-')[0];
    app.store
      .find<Item>('review-items', id, { include: 'category,category.criteria,user' })
      .then((item) => {
        this.item = item;
        app.setTitle(item.name());
        this.loadReviews(0);
        app.request({ method: 'POST', url: `${app.forum.attribute('apiUrl')}/review-items/${item.id()}/view` }).catch(() => {
          /* a view that isn't counted is not worth an alert */
        });
      })
      .catch(failed)
      .finally(() => {
        this.loading = false;
        m.redraw();
      });
  }

  /** Recounts after a review or comment changed: the item row (counts, averages) and the review list. */
  private refresh(): void {
    if (!this.item) return;
    app.store
      .find<Item>('review-items', this.item.id()!)
      .then(() => m.redraw())
      .catch(failed);
    this.loadReviews(0);
  }

  private loadReviews(offset: number): void {
    if (!this.item) return;
    this.loadingReviews = true;
    const filter: Record<string, string> = { item: this.item.id()! };
    if (this.withPhotos) filter.photos = '1';
    const direction = this.sort === 'createdAt' ? (this.newestFirst ? '-' : '') : '-';
    app.store
      .find<Review[]>('reviews', {
        filter,
        sort: direction + this.sort,
        include: 'user,merchant',
        page: { offset, limit: Number(setting('reviews_per_page')), total: 0 },
      })
      .then((list) => {
        this.reviews = offset ? [...this.reviews, ...list] : [...list];
        this.moreReviews = !!list.payload?.links?.next;
      })
      .catch(failed)
      .finally(() => {
        this.loadingReviews = false;
        m.redraw();
      });
  }

  view() {
    const item = this.item;
    return (
      <div className="CommunityReviewsPage">
        <PageStructure
          className="CommunityReviewsPage-structure CR-itempage"
          loading={this.loading}
          hero={() => (item ? this.hero(item) : null)}
          sidebar={() => <IndexSidebar />}
        >
          {item ? this.body(item) : null}
        </PageStructure>
        {item?.canReview() ? (
          <button type="button" className="CR-fab" aria-label={tx('item.write')} onclick={() => this.write()}>
            <i className="fas fa-pen" aria-hidden="true" />
          </button>
        ) : null}
      </div>
    );
  }

  private write(): void {
    if (this.item) openComposer({ item: this.item, onsaved: () => this.refresh() });
  }

  private hero(item: Item): Mithril.Children {
    const category = item.category();
    return (
      <div className="CR-itemhero">
        <div className="CR-itemhero-in">
          <nav className="CR-crumbs" aria-label={tx('item.breadcrumbs')}>
            <Link href={app.route('community-reviews.index')}>{t('page.title')}</Link>
            {category ? [' › ', <Link href={app.route('community-reviews.category', { slug: category.slug() })}>{category.name()}</Link>] : null}
          </nav>
          <div className="CR-itemhero-art">{art(item, true)}</div>
          <div className="CR-iteminfo">
            {item.status() === 'pending' ? <span className="CR-badge CR-badge--warn">{t('item.pending')}</span> : null}
            <h1>{item.name()}</h1>
            <div className="CR-muted">
              {item.brand() ? [t('item.by', { brand: item.brand() }), ' · '] : null}
              {item.user()
                ? [t('item.added_by'), ' ', <Link href={app.route.user(one(item.user())!)}>{username(one(item.user())!)}</Link>, ' · ']
                : null}
              <i className="far fa-eye" aria-hidden="true" /> {t('item.views', { count: count(item.viewCount()) })}
            </div>
            {this.specs(item)}
            <div className="CR-actions">
              {item.canReview() ? (
                <Button className="Button Button--primary" icon="fas fa-pen" onclick={() => this.write()}>
                  {t('item.write')}
                </Button>
              ) : null}
              <a className="Button" href="#comments">
                <i className="Button-icon far fa-comments" aria-hidden="true" />
                <span className="Button-label">{t('item.comments', { count: item.commentCount() })}</span>
              </a>
            </div>
            {this.modTools(item)}
          </div>
          {this.scorePanel(item)}
        </div>
      </div>
    );
  }

  private specs(item: Item): Mithril.Children {
    const defined = one(item.category())?.itemFields() || [];
    const values = item.fields() || {};
    const shown = defined.filter((f) => values[f.key]);
    if (!shown.length) return null;
    return (
      <dl className="CR-specs">
        {shown.map((f) => (
          <div className="CR-spec">
            <dt>{f.label}</dt>
            <dd>{values[f.key]}</dd>
          </div>
        ))}
      </dl>
    );
  }

  private modTools(item: Item): Mithril.Children {
    const entries: MenuEntry[] = [];
    if (item.canEdit())
      entries.push({ icon: 'fas fa-pen', label: t('item.edit'), run: () => openComposer({ item, editItem: true, onsaved: () => this.refresh() }) });
    if (item.canModerate() && item.status() === 'pending') {
      entries.push({
        icon: 'fas fa-check',
        label: t('item.approve'),
        run: () =>
          item
            .save({ status: 'approved' })
            .then(() => m.redraw())
            .catch(failed),
      });
    }
    if (item.canModerate()) entries.push({ icon: 'fas fa-code-merge', label: t('item.merge'), run: () => openMerge(item) });
    if (item.canDelete()) {
      entries.push({
        icon: 'far fa-trash-can',
        label: t('item.delete'),
        run: () => {
          if (!confirm(tx('item.delete_confirm'))) return;
          item
            .delete()
            .then(() => m.route.set(app.route('community-reviews.index')))
            .catch(failed);
        },
      });
    }
    if (item.canReport() && app.session.user?.id() !== one(item.user())?.id()) {
      entries.push({ icon: 'far fa-flag', label: t('report.action'), run: () => openReport('item', Number(item.id())) });
    }
    if (!entries.length) return null;
    return (
      <div className="CR-modtools">
        <Menu
          entries={entries}
          label={tx('item.controls')}
          right={false}
          toggleClassName="Button Button--flat"
          toggle={[<i className="Button-icon fas fa-ellipsis-h" aria-hidden="true" />, <span className="Button-label">{t('item.more')}</span>]}
        />
      </div>
    );
  }

  private scorePanel(item: Item): Mithril.Children {
    const dist = item.distribution() || [0, 0, 0, 0, 0];
    const total = Math.max(
      1,
      dist.reduce((a, b) => a + b, 0)
    );
    const rating = item.rating();
    return (
      <div className="CR-scorepanel">
        <div className="CR-scorepanel-top">
          {scoreTile(rating, 'CR-score--lg')}
          <div>
            <div className="CR-scorepanel-word">{rating === null ? t('item.no_ratings') : t(`words.${Math.max(1, Math.round(rating))}`)}</div>
            {rating !== null ? stars(rating) : null}
            <div className="CR-muted">
              {t('item.review_count', { count: item.reviewCount() })}
              {rating !== null && !ranked(item) ? ` · ${tx('card.not_ranked')}` : ''}
            </div>
          </div>
        </div>
        <div className="CR-bars">
          {[5, 4, 3, 2, 1].map((star) => (
            <div className="CR-bar">
              <span>{star}★</span>
              <span className="CR-bar-track">
                <span className="CR-bar-fill CR-bar-fill--star" style={{ width: `${Math.round((dist[star - 1] / total) * 100)}%` }} />
              </span>
              <b>{dist[star - 1]}</b>
            </div>
          ))}
        </div>
      </div>
    );
  }

  private body(item: Item): Mithril.Children {
    return (
      <div className="CR-itembody">
        <div className="CR-itembody-main">
          {this.reviewsBar(item)}
          {this.reviews.map((review) => (
            <ReviewCard
              key={review.id()}
              review={review}
              item={item}
              onreply={(r: Review) => {
                this.replyTo = r;
                document.getElementById('comments')?.scrollIntoView({ behavior: 'smooth' });
              }}
              onchange={() => this.refresh()}
            />
          ))}
          {!this.reviews.length && !this.loadingReviews ? <p className="CR-empty">{t('item.no_reviews')}</p> : null}
          {this.loadingReviews ? <LoadingIndicator /> : null}
          {this.moreReviews && !this.loadingReviews ? (
            <Button className="Button Button--block CR-more" onclick={() => this.loadReviews(this.reviews.length)}>
              {t('item.load_more')}
            </Button>
          ) : null}
          <Comments item={item} replyTo={this.replyTo} onclearreply={() => (this.replyTo = null)} onchange={() => this.refresh()} />
        </div>
        <aside className="CR-itembody-side">
          {this.criteriaCard(item)}
          {this.mentionsCard(item)}
        </aside>
      </div>
    );
  }

  private reviewsBar(item: Item): Mithril.Children {
    return (
      <div className="CR-sectionhead CR-reviewsbar">
        <h2>{t('item.reviews_title', { count: item.reviewCount() })}</h2>
        <span className="CR-grow" />
        <Menu
          label={tx('sort.label')}
          toggleClassName="Button"
          toggle={[
            <i className="Button-icon fas fa-arrow-down-wide-short" aria-hidden="true" />,
            <span className="Button-label">{this.sortLabel()}</span>,
          ]}
          entries={this.sortEntries()}
        />
        <button
          type="button"
          className={classList('CR-chip', this.withPhotos && 'is-active')}
          aria-pressed={this.withPhotos ? 'true' : 'false'}
          onclick={() => {
            this.withPhotos = !this.withPhotos;
            this.loadReviews(0);
          }}
        >
          <i className="fas fa-camera" aria-hidden="true" /> {t('item.with_photos')}
        </button>
      </div>
    );
  }

  private sortLabel(): Mithril.Children {
    if (this.sort === 'createdAt') return t(this.newestFirst ? 'item.sort_newest' : 'item.sort_oldest');
    return t(`item.sort_${this.sort}`);
  }

  private sortEntries(): MenuEntry[] {
    const pick =
      (sort: ReviewSort, newest = true) =>
      () => {
        this.sort = sort;
        this.newestFirst = newest;
        this.loadReviews(0);
      };
    const entries: MenuEntry[] = [
      { icon: 'fas fa-fw', label: t('item.sort_newest'), active: this.sort === 'createdAt' && this.newestFirst, run: pick('createdAt', true) },
      { icon: 'fas fa-fw', label: t('item.sort_oldest'), active: this.sort === 'createdAt' && !this.newestFirst, run: pick('createdAt', false) },
      { icon: 'fas fa-fw', label: t('item.sort_rating'), active: this.sort === 'rating', run: pick('rating') },
    ];
    if (setting('extra_helpful'))
      entries.push({ icon: 'fas fa-fw', label: t('item.sort_helpful'), active: this.sort === 'helpful', run: pick('helpful') });
    return entries.map((e) => ({ ...e, icon: e.active ? 'fas fa-check' : 'fas fa-fw' }));
  }

  private criteriaCard(item: Item): Mithril.Children {
    const averages = item.criteriaAverages() || {};
    const criteria = (one(item.category())?.criteria() || []).filter(Boolean);
    if (!criteria.length || !Object.keys(averages).length) return null;
    return (
      <div className="CR-sidecard">
        <h4>{t('item.by_criterion')}</h4>
        {criteria.map((c) => {
          const avg = averages[String(c!.id())];
          if (avg === undefined) return null;
          return (
            <div className="CR-meter">
              <span>{c!.name()}</span>
              <b>{avg.toFixed(1)}</b>
              <span className="CR-seg" aria-hidden="true">
                {[1, 2, 3, 4, 5].map((n) => (
                  <i className={avg >= n ? 'on' : avg >= n - 0.5 ? 'half' : ''} />
                ))}
              </span>
            </div>
          );
        })}
      </div>
    );
  }

  private mentionsCard(item: Item): Mithril.Children {
    const mentions = item.topMentions();
    if (!mentions) return null;
    return (
      <div className="CR-sidecard">
        <h4>{t('item.most_mentioned')}</h4>
        <div className="CR-mentions">
          {Object.entries(mentions.pros || {}).map(([label, n]) => (
            <span className="CR-pro">
              {label} · {n}
            </span>
          ))}
          {Object.entries(mentions.cons || {}).map(([label, n]) => (
            <span className="CR-con">
              {label} · {n}
            </span>
          ))}
        </div>
      </div>
    );
  }
}
