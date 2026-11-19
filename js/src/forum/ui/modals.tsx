import app from 'flarum/forum/app';
import FormModal, { type IFormModalAttrs } from 'flarum/common/components/FormModal';
import Modal, { type IInternalModalAttrs } from 'flarum/common/components/Modal';
import Button from 'flarum/common/components/Button';
import type Mithril from 'mithril';
import type Item from '../models/Item';
import type { ReviewPhoto } from '../models/Review';
import { scoreTile } from './bits';
import { t, tx } from '../util';

interface ReportAttrs extends IFormModalAttrs {
  subjectType: 'item' | 'review' | 'comment';
  subjectId: number;
}

/** MyBB's Report link: a reason (optional), sent to the moderators' queue. */
export class ReportModal extends FormModal<ReportAttrs> {
  private reason = '';

  className() {
    return 'CR-report Modal--small';
  }

  title() {
    return t('report.title');
  }

  content() {
    return (
      <div className="Modal-body">
        <div className="Form">
          <div className="Form-group">
            <label for="cr-reason">{t('report.reason')}</label>
            <textarea
              id="cr-reason"
              className="FormControl"
              rows={3}
              maxlength={500}
              value={this.reason}
              oninput={(e: InputEvent) => (this.reason = (e.target as HTMLTextAreaElement).value)}
            />
          </div>
          <div className="Form-group">
            <Button type="submit" className="Button Button--primary Button--block" loading={this.loading}>
              {t('report.send')}
            </Button>
          </div>
        </div>
      </div>
    );
  }

  onsubmit(e: SubmitEvent): void {
    e.preventDefault();
    this.loading = true;
    app.store
      .createRecord('review-reports')
      .save({ subjectType: this.attrs.subjectType, subjectId: this.attrs.subjectId, reason: this.reason })
      .then(
        () => {
          this.hide();
          app.alerts.show({ type: 'success' }, tx('report.sent'));
        },
        (error) => {
          this.loading = false;
          this.onerror(error);
          m.redraw();
        }
      );
  }
}

interface MergeAttrs extends IFormModalAttrs {
  item: Item;
}

/** MyBB "Merge products", by name instead of IDs: the reviews and comments move to the item picked. */
export class MergeModal extends FormModal<MergeAttrs> {
  private query = '';
  private results: Item[] = [];
  private target: Item | null = null;
  private timer = 0;

  className() {
    return 'CR-merge Modal--small';
  }

  title() {
    return t('merge.title', { name: this.attrs.item.name() });
  }

  content() {
    return (
      <div className="Modal-body">
        <div className="Form">
          <p className="helpText">{t('merge.help')}</p>
          <div className="Form-group">
            <label for="cr-merge-q">{t('merge.search')}</label>
            <input
              id="cr-merge-q"
              className="FormControl"
              value={this.query}
              oninput={(e: InputEvent) => this.search((e.target as HTMLInputElement).value)}
            />
          </div>
          <div className="CR-similar">
            {this.results.map((item) => (
              <button
                type="button"
                className={'CR-similar-row' + (this.target === item ? ' is-active' : '')}
                aria-pressed={this.target === item ? 'true' : 'false'}
                onclick={() => (this.target = item)}
              >
                <span>{item.name()}</span>
                {scoreTile(item.rating(), 'CR-score--sm')}
              </button>
            ))}
          </div>
          <div className="Form-group">
            <Button type="submit" className="Button Button--primary Button--block" loading={this.loading} disabled={!this.target}>
              {this.target ? t('merge.into', { name: this.target.name() }) : t('merge.pick')}
            </Button>
          </div>
        </div>
      </div>
    );
  }

  private search(value: string): void {
    this.query = value;
    clearTimeout(this.timer);
    if (value.trim().length < 2) return;
    this.timer = window.setTimeout(() => {
      app.store
        .find<Item[]>('review-items', { filter: { q: value.trim() }, page: { limit: 6, total: 0 } })
        .then((items) => (this.results = items.filter((i) => i !== this.attrs.item)))
        .catch(() => (this.results = []))
        .finally(() => m.redraw());
    }, 300);
  }

  onsubmit(e: SubmitEvent): void {
    e.preventDefault();
    if (!this.target) return;
    this.loading = true;
    const target = this.target;
    app
      .request({
        method: 'POST',
        url: `${app.forum.attribute('apiUrl')}/review-items/${this.attrs.item.id()}/merge`,
        body: { targetId: Number(target.id()) },
      })
      .then(
        () => {
          this.hide();
          m.route.set(app.route('community-reviews.item', { id: target.urlId() }));
        },
        (error) => {
          this.loading = false;
          this.onerror(error);
          m.redraw();
        }
      );
  }
}

interface LightboxAttrs extends IInternalModalAttrs {
  photos: ReviewPhoto[];
  index: number;
}

/** A review's photos full size, with previous and next (MyBB uses blueimp Gallery). */
export class Lightbox extends Modal<LightboxAttrs> {
  private index = 0;

  oninit(vnode: Mithril.Vnode<LightboxAttrs, this>) {
    super.oninit(vnode);
    this.index = this.attrs.index;
  }

  className() {
    return 'CR-lightbox Modal--large';
  }

  title() {
    return t('lightbox.title', { n: this.index + 1, total: this.attrs.photos.length });
  }

  content() {
    const photos = this.attrs.photos;
    const photo = photos[this.index];
    const go = (step: number) => () => (this.index = (this.index + step + photos.length) % photos.length);
    return (
      <div className="Modal-body CR-lightbox-body">
        <img src={photo.url} alt="" />
        {photos.length > 1 ? (
          <div className="CR-lightbox-nav">
            <Button className="Button" icon="fas fa-chevron-left" onclick={go(-1)}>
              {t('lightbox.prev')}
            </Button>
            <Button className="Button" icon="fas fa-chevron-right" onclick={go(1)}>
              {t('lightbox.next')}
            </Button>
          </div>
        ) : null}
      </div>
    );
  }
}
