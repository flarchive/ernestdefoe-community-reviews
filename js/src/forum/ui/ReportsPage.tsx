import app from 'flarum/forum/app';
import Page from 'flarum/common/components/Page';
import Button from 'flarum/common/components/Button';
import LoadingIndicator from 'flarum/common/components/LoadingIndicator';
import Link from 'flarum/common/components/Link';
import humanTime from 'flarum/common/helpers/humanTime';
import username from 'flarum/common/helpers/username';
import classList from 'flarum/common/utils/classList';
import type Mithril from 'mithril';
import type Item from '../models/Item';
import type Report from '../models/Report';
import type Review from '../models/Review';
import { failed, setting, t, tx } from '../util';
import { IndexSidebar, PageStructure } from './core';
import { itemCard, reviewRow } from './cards';

type Tab = 'reports' | 'items' | 'reviews';

/** `/reviews/reports`: MyBB's reported content for Reviews, plus anything waiting for approval. Moderators only. */
export default class ReportsPage extends Page {
  private tab: Tab = 'reports';
  private reports: Report[] = [];
  private items: Item[] = [];
  private reviews: Review[] = [];
  private loading = true;

  oninit(vnode: Mithril.Vnode) {
    super.oninit(vnode);
    this.bodyClass = 'CommunityReviewsPage-body';
    app.setTitle(tx('reports.title'));
    this.load();
  }

  private tabs(): Tab[] {
    const tabs: Tab[] = ['reports'];
    if (setting('item_approval')) tabs.push('items');
    if (setting('review_approval')) tabs.push('reviews');
    return tabs;
  }

  private load(): void {
    this.loading = true;
    const page = { limit: 50, total: 0 };
    const request =
      this.tab === 'reports'
        ? app.store.find<Report[]>('review-reports', { include: 'item,user', page }).then((r) => (this.reports = [...r]))
        : this.tab === 'items'
          ? app.store.find<Item[]>('review-items', { filter: { status: 'pending' }, page }).then((r) => (this.items = [...r]))
          : app.store.find<Review[]>('reviews', { filter: { status: 'pending' }, include: 'user,item', page }).then((r) => (this.reviews = [...r]));
    request.catch(failed).finally(() => {
      this.loading = false;
      m.redraw();
    });
  }

  view() {
    return (
      <div className="CommunityReviewsPage">
        <PageStructure className="CommunityReviewsPage-structure" sidebar={() => <IndexSidebar />}>
          <div className="CR-cathead">
            <h2>{t('reports.title')}</h2>
          </div>
          {this.tabs().length > 1 ? (
            <div className="CR-chips" role="tablist">
              {this.tabs().map((tab) => (
                <button
                  type="button"
                  role="tab"
                  aria-selected={this.tab === tab ? 'true' : 'false'}
                  className={classList('CR-chip', this.tab === tab && 'is-active')}
                  onclick={() => {
                    this.tab = tab;
                    this.load();
                  }}
                >
                  {t(`reports.tab_${tab}`)}
                </button>
              ))}
            </div>
          ) : null}
          {this.loading ? <LoadingIndicator /> : this.list()}
        </PageStructure>
      </div>
    );
  }

  private list(): Mithril.Children {
    if (this.tab === 'items')
      return this.items.length ? (
        <div className="CR-grid">{this.items.map((i) => itemCard(i))}</div>
      ) : (
        <p className="CR-empty">{t('reports.none_pending')}</p>
      );
    if (this.tab === 'reviews')
      return this.reviews.length ? (
        <div className="CR-rows">{this.reviews.map(reviewRow)}</div>
      ) : (
        <p className="CR-empty">{t('reports.none_pending')}</p>
      );
    if (!this.reports.length) return <p className="CR-empty">{t('reports.none')}</p>;
    return <div className="CR-reports">{this.reports.map((r) => this.report(r))}</div>;
  }

  private report(report: Report): Mithril.Children {
    const item = report.item();
    const anchor =
      report.subjectType() === 'review' ? `#review${report.subjectId()}` : report.subjectType() === 'comment' ? `#comment${report.subjectId()}` : '';
    return (
      <div className="CR-reportrow" key={report.id()}>
        <div className="CR-reportrow-main">
          <div>
            <span className="CR-badge">{t(`reports.kind_${report.subjectType()}`)}</span>{' '}
            {item ? <Link href={app.route('community-reviews.item', { id: item.urlId() }) + anchor}>{item.name()}</Link> : null}
          </div>
          {report.excerpt() ? <p className="CR-reportrow-excerpt">“{report.excerpt()}”</p> : null}
          <div className="CR-muted">
            {t('reports.by', { name: username(report.user() || null) })} · {humanTime(report.createdAt())}
            {report.reason() ? ` · ${report.reason()}` : ''}
          </div>
        </div>
        <Button
          className="Button"
          icon="fas fa-check"
          onclick={() =>
            report
              .save({ resolved: true })
              .then(() => (this.reports = this.reports.filter((r) => r !== report)))
              .catch(failed)
              .finally(() => m.redraw())
          }
        >
          {t('reports.resolve')}
        </Button>
      </div>
    );
  }
}
