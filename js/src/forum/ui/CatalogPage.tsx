import app from 'flarum/forum/app';
import Page from 'flarum/common/components/Page';
import Button from 'flarum/common/components/Button';
import LoadingIndicator from 'flarum/common/components/LoadingIndicator';
import Link from 'flarum/common/components/Link';
import classList from 'flarum/common/utils/classList';
import type Mithril from 'mithril';
import type Category from '../models/Category';
import type Item from '../models/Item';
import type Review from '../models/Review';
import { failed, setting, t, tx } from '../util';
import { IndexSidebar, PageStructure } from './core';
import { art, count, one, scoreTile, stars } from './bits';
import { itemCard, reviewRow } from './cards';
import { openAddItem } from './open';
import Menu from './Menu';

type Sort = 'name' | 'rating' | 'reviewCount' | 'viewCount' | 'createdAt' | 'lastActivityAt' | 'rank';
const SORTS: Sort[] = ['name', 'rating', 'reviewCount', 'viewCount', 'createdAt', 'lastActivityAt', 'rank'];
const DEFAULTS: Record<string, Sort> = { name: 'name', rating: 'rating', reviews: 'reviewCount', views: 'viewCount', date: 'createdAt' };

interface Stats {
  items: number;
  reviews: number;
  comments: number;
  views: number;
}

/**
 * `/reviews` (the catalog home: hero, recently added, latest reviews) and `/reviews/c/:slug` (one category, sorted
 * by the admin's default or the member's pick). Search runs on the home page.
 */
export default class CatalogPage extends Page {
  private categories: Category[] = [];
  private category: Category | null = null;
  private items: Item[] = [];
  private reviews: Review[] = [];
  private stats: Stats | null = null;
  private spotlight: Item | null = null;
  private quote: Review | null = null;
  private loading = true;
  private moreItems = false;
  private moreReviews = false;
  private sort: Sort = 'name';
  private desc = false;
  private query = '';
  private searched = '';

  oninit(vnode: Mithril.Vnode) {
    super.oninit(vnode);
    this.bodyClass = 'CommunityReviewsPage-body';
    this.sort = DEFAULTS[String(setting('item_order'))] || 'name';
    this.desc = setting('item_order_dir') === 'desc';
    this.boot();
  }

  private boot(): void {
    app.store
      .find<Category[]>('review-categories')
      .then((categories) => {
        this.categories = [...categories];
        const slug = m.route.param('slug');
        this.category = slug ? this.categories.find((c) => c.slug() === slug) || null : null;
        app.setTitle(this.category ? this.category.name() : tx('page.title'));
        this.load();
        if (!this.category) this.loadHome();
      })
      .catch(failed);
  }

  private loadHome(): void {
    app
      .request<{ data: { attributes: Stats } }>({ method: 'GET', url: `${app.forum.attribute('apiUrl')}/review-items/stats` })
      .then((r) => (this.stats = r.data.attributes))
      .catch(() => (this.stats = null))
      .finally(() => m.redraw());

    app.store
      .find<Item[]>('review-items', { sort: '-rank', page: { limit: 1, total: 0 } })
      .then((items) => {
        this.spotlight = items[0] || null;
        if (!this.spotlight) return;
        return app.store
          .find<Review[]>('reviews', { filter: { item: this.spotlight.id()! }, sort: '-rating', page: { limit: 1, total: 0 } })
          .then((r) => {
            this.quote = r[0] || null;
          });
      })
      .catch(failed)
      .finally(() => m.redraw());

    this.loadReviews(0);
  }

  private load(offset = 0): void {
    this.loading = true;
    const home = !this.category && !this.searched;
    const limit = home ? Number(setting('recent_limit')) : Number(setting('items_per_page'));
    const filter: Record<string, string> = {};
    if (this.category) filter.category = this.category.id()!;
    if (this.searched) filter.q = this.searched;
    app.store
      .find<Item[]>('review-items', {
        filter,
        sort: home ? '-createdAt' : (this.desc ? '-' : '') + this.sort,
        page: { offset, limit, total: 0 },
      })
      .then((items) => {
        this.items = offset ? [...this.items, ...items] : [...items];
        this.moreItems = !!items.payload?.links?.next;
      })
      .catch(failed)
      .finally(() => {
        this.loading = false;
        m.redraw();
      });
  }

  private loadReviews(offset: number): void {
    const limit = Number(setting('recent_limit'));
    app.store
      .find<Review[]>('reviews', { include: 'user,item,item.category', page: { offset, limit, total: 0 } })
      .then((reviews) => {
        this.reviews = offset ? [...this.reviews, ...reviews] : [...reviews];
        this.moreReviews = !!reviews.payload?.links?.next;
      })
      .catch(failed)
      .finally(() => m.redraw());
  }

  view() {
    return (
      <div className="CommunityReviewsPage">
        <PageStructure className="CommunityReviewsPage-structure" hero={() => (this.category ? null : this.hero())} sidebar={() => <IndexSidebar />}>
          {this.category ? this.categoryView() : this.homeView()}
        </PageStructure>
        {app.forum.attribute('communityReviewsCanAddItems') ? (
          <button type="button" className="CR-fab" aria-label={tx('page.add_item')} onclick={() => openAddItem(this.category)}>
            <i className="fas fa-plus" aria-hidden="true" />
          </button>
        ) : null}
      </div>
    );
  }

  private hero(): Mithril.Children {
    const s = this.stats;
    return (
      <div className="CR-hero">
        <div className="CR-hero-in">
          <div className="CR-hero-text">
            <span className="CR-hero-kicker">
              <i className="fas fa-star-half-stroke" aria-hidden="true" /> {t('hero.kicker')}
            </span>
            <h2 className="CR-hero-title">{t('hero.title')}</h2>
            <form
              className="CR-hero-search"
              role="search"
              onsubmit={(e: SubmitEvent) => {
                e.preventDefault();
                this.searched = this.query.trim();
                this.load();
              }}
            >
              <i className="fas fa-magnifying-glass" aria-hidden="true" />
              <input
                type="search"
                aria-label={tx('hero.search')}
                placeholder={tx('hero.search_placeholder', { count: s ? s.items : '' })}
                value={this.query}
                oninput={(e: InputEvent) => (this.query = (e.target as HTMLInputElement).value)}
              />
              <Button type="submit" className="Button Button--primary">
                {t('hero.search')}
              </Button>
            </form>
            {s ? (
              <div className="CR-hero-stats">
                {(['items', 'reviews', 'comments', 'views'] as const).map((key) => (
                  <div>
                    <b>{count(s[key])}</b>
                    <span>{t(`stats.${key}`)}</span>
                  </div>
                ))}
              </div>
            ) : null}
          </div>
          {this.spotlight ? this.spotlightCard(this.spotlight) : null}
        </div>
      </div>
    );
  }

  private spotlightCard(item: Item): Mithril.Children {
    const quote = this.quote?.scores()?.[0]?.comment || this.quote?.title();
    return (
      <Link className="CR-spot" href={app.route('community-reviews.item', { id: item.urlId() })}>
        <span className="CR-spot-label">
          <i className="fas fa-trophy" aria-hidden="true" /> {t('hero.spotlight')}
        </span>
        <div className="CR-spot-row">
          {art(item)}
          <div className="CR-spot-main">
            {item.brand() ? <div className="CR-card-brand">{item.brand()}</div> : null}
            <div className="CR-spot-name">{item.name()}</div>
            <div className="CR-spot-rate">
              {scoreTile(item.rating())}
              {stars(item.rating())}
            </div>
          </div>
        </div>
        {quote ? (
          <p className="CR-spot-quote">
            “{quote}”<span>{one(this.quote?.user())?.displayName()}</span>
          </p>
        ) : null}
      </Link>
    );
  }

  private homeView(): Mithril.Children {
    return [
      this.categoryChips(),
      <div className="CR-sectionhead">
        <h2>{this.searched ? t('page.results_for', { q: this.searched }) : t('page.recent_items')}</h2>
        {this.searched ? (
          <Button
            className="Button Button--link"
            onclick={() => {
              this.searched = '';
              this.query = '';
              this.load();
            }}
          >
            {t('page.clear_search')}
          </Button>
        ) : null}
      </div>,
      this.grid(this.searched ? 'page.no_results' : 'page.empty'),
      this.searched ? null : (
        <div className="CR-latest">
          <div className="CR-sectionhead">
            <h2>{t('page.latest_reviews')}</h2>
          </div>
          {this.reviews.length ? <div className="CR-rows">{this.reviews.map(reviewRow)}</div> : <p className="CR-empty">{t('page.no_reviews')}</p>}
          {this.moreReviews ? (
            <Button className="Button Button--block CR-more" onclick={() => this.loadReviews(this.reviews.length)}>
              {t('page.load_more')}
            </Button>
          ) : null}
        </div>
      ),
    ];
  }

  private categoryView(): Mithril.Children {
    const category = this.category!;
    return [
      this.categoryChips(),
      <div className="CR-cathead">
        <div>
          <h2>{category.name()}</h2>
          <p className="CR-muted">
            {t('page.category_counts', { items: category.itemCount(), reviews: category.reviewCount() })}
            {category.description() ? ` · ${category.description()}` : ''}
          </p>
        </div>
        {app.forum.attribute('communityReviewsCanAddItems') ? (
          <Button className="Button Button--primary CR-cathead-add" icon="fas fa-plus" onclick={() => openAddItem(category)}>
            {t('page.add_item')}
          </Button>
        ) : null}
      </div>,
      this.toolbar(),
      this.grid('page.category_empty'),
    ];
  }

  /** Phones: the categories as a row of chips (the sidebar folds into a dropdown there). */
  private categoryChips(): Mithril.Children {
    if (!this.categories.length) return null;
    return (
      <nav className="CR-chips" aria-label={tx('page.categories')}>
        <Link className={classList('CR-chip', !this.category && 'is-active')} href={app.route('community-reviews.index')}>
          {t('page.all')}
        </Link>
        {this.categories.map((c) => (
          <Link
            className={classList('CR-chip', this.category === c && 'is-active')}
            href={app.route('community-reviews.category', { slug: c.slug() })}
          >
            {c.name()}
          </Link>
        ))}
      </nav>
    );
  }

  private toolbar(): Mithril.Children {
    return (
      <div className="CR-toolbar">
        <Menu
          label={tx('sort.label')}
          right={false}
          toggleClassName="Button"
          toggle={[
            <i className="Button-icon fas fa-arrow-down-wide-short" aria-hidden="true" />,
            <span className="Button-label">
              <span className="CR-toolbar-long">{t('sort.label')} </span>
              {t(`sort.${this.sort}`)} <i className="fas fa-caret-down" aria-hidden="true" />
            </span>,
          ]}
          entries={SORTS.map((sort) => ({
            icon: this.sort === sort ? 'fas fa-check' : 'fas fa-fw',
            label: t(`sort.${sort}`),
            active: this.sort === sort,
            run: () => {
              this.sort = sort;
              this.desc = sort !== 'name';
              this.load();
            },
          }))}
        />
        <Button
          className="Button"
          icon={this.desc ? 'fas fa-arrow-down-9-1' : 'fas fa-arrow-up-1-9'}
          aria-label={tx('sort.direction')}
          onclick={() => {
            this.desc = !this.desc;
            this.load();
          }}
        >
          <span className="CR-toolbar-long">{t(this.desc ? 'sort.desc' : 'sort.asc')}</span>
        </Button>
      </div>
    );
  }

  private grid(emptyKey: string): Mithril.Children {
    const rankStart = this.sort === 'rank' && this.desc && this.category;
    return [
      this.items.length ? (
        <div className="CR-grid">{this.items.map((item, i) => itemCard(item, rankStart ? i + 1 : undefined))}</div>
      ) : this.loading ? null : (
        <p className="CR-empty">{t(emptyKey)}</p>
      ),
      this.loading ? <LoadingIndicator /> : null,
      this.moreItems && !this.loading && (this.category || this.searched) ? (
        <Button className="Button Button--block CR-more" onclick={() => this.load(this.items.length)}>
          {t('page.load_more')}
        </Button>
      ) : null,
    ];
  }
}
