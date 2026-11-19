import app from 'flarum/forum/app';
import FormModal, { type IFormModalAttrs } from 'flarum/common/components/FormModal';
import Button from 'flarum/common/components/Button';
import classList from 'flarum/common/utils/classList';
import type Mithril from 'mithril';
import type Category from '../models/Category';
import type { ItemField } from '../models/Category';
import type Criterion from '../models/Criterion';
import type Item from '../models/Item';
import type Review from '../models/Review';
import type { ReviewPhoto } from '../models/Review';
import { setting, t, tx } from '../util';
import { scoreTile, one } from './bits';
import PhotoPicker from './PhotoPicker';

export interface ComposerAttrs extends IFormModalAttrs {
  /** Writing about this item (new review, or editing one); absent when adding an item. */
  item?: Item;
  review?: Review;
  /** Edit the item's own details (name, brand, specs) instead of a review. */
  editItem?: boolean;
  /** Adding an item: the category to start in. */
  category?: Category | null;
  onsaved?: (item?: Item) => void;
}

interface Score {
  score: number;
  comment: string;
}

/**
 * Adding an item (MyBB: name + category, then its first review, sent together), writing or editing a review
 * (stars and a comment per criterion, General thoughts, price, link, merchant, photos, the extras the admin
 * turned on), or editing an item's details.
 */
export default class Composer extends FormModal<ComposerAttrs> {
  private categories: Category[] = [];
  private categoryId = '';
  private name = '';
  private brand = '';
  private fields: Record<string, string> = {};
  private similar: Item[] = [];
  private similarTimer = 0;
  private step: 1 | 2 = 1;

  private scores: Record<string, Score> = {};
  private reviewTitle = '';
  private body = '';
  private price = '';
  private url = '';
  private merchant = '';
  private ownedFor = '';
  private pros = '';
  private cons = '';
  private photos: ReviewPhoto[] = [];

  oninit(vnode: Mithril.Vnode<ComposerAttrs, this>) {
    super.oninit(vnode);
    const { item, review, category } = this.attrs;
    this.categories = app.store.all<Category>('review-categories').sort((a, b) => a.position() - b.position());
    this.categoryId = (item?.category() || null)?.id() || category?.id() || this.categories[0]?.id() || '';
    if (item) {
      this.name = item.name();
      this.brand = item.brand() || '';
      this.fields = { ...(item.fields() || {}) };
    }
    if (review) {
      (review.scores() || []).forEach((s) => (this.scores[String(s.criterionId)] = { score: s.score, comment: s.comment || '' }));
      this.reviewTitle = review.title() || '';
      this.body = review.body() || '';
      this.price = review.price() || '';
      this.url = review.url() || '';
      this.merchant = one(review.merchant())?.username() || '';
      this.ownedFor = review.ownedFor() || '';
      this.pros = (review.pros() || []).join(', ');
      this.cons = (review.cons() || []).join(', ');
      this.photos = [...(review.photos() || [])];
    }
  }

  className() {
    return 'CR-composer Modal--large';
  }

  title() {
    const { item, review, editItem } = this.attrs;
    if (editItem) return t('composer.edit_item');
    if (!item)
      return [
        <small>{t('composer.step', { step: this.step })}</small>,
        this.step === 1 ? t('composer.add_item') : t('composer.first_review', { name: this.name }),
      ];
    return [<small>{t(review ? 'composer.edit_review' : 'composer.write_review')}</small>, item.name()];
  }

  private addingItem(): boolean {
    return !this.attrs.item;
  }

  private category(): Category | undefined {
    return this.categories.find((c) => c.id() === this.categoryId);
  }

  private criteria(): Criterion[] {
    return ((one(this.category())?.criteria() || []).filter(Boolean) as Criterion[]).sort((a, b) => a.position() - b.position());
  }

  content() {
    const showItem = this.attrs.editItem || (this.addingItem() && this.step === 1);
    return (
      <div className="Modal-body">
        <div className="Form">
          {showItem ? this.itemFields() : this.reviewFields()}
          <div className="Form-group CR-composer-foot">{this.buttons(showItem)}</div>
        </div>
      </div>
    );
  }

  private buttons(showItem: boolean): Mithril.Children {
    if (this.addingItem() && this.step === 1) {
      return [
        <Button className="Button" onclick={() => this.hide()}>
          {t('composer.cancel')}
        </Button>,
        <Button className="Button Button--primary" onclick={() => this.next()} disabled={!this.name.trim() || !this.categoryId}>
          {t('composer.next')} <i className="fas fa-arrow-right" aria-hidden="true" />
        </Button>,
      ];
    }
    return [
      <span className="CR-muted CR-grow">{showItem ? '' : t('composer.edit_later')}</span>,
      this.addingItem() ? (
        <Button className="Button" onclick={() => (this.step = 1)}>
          {t('composer.back')}
        </Button>
      ) : null,
      <Button type="submit" className="Button Button--primary" loading={this.loading} icon={showItem ? 'fas fa-check' : 'fas fa-paper-plane'}>
        {showItem ? t('composer.save_item') : t(this.attrs.review ? 'composer.save_review' : 'composer.post_review')}
      </Button>,
    ];
  }

  private next(): void {
    if (!this.name.trim() || !this.categoryId) return;
    this.step = 2;
  }

  private itemFields(): Mithril.Children {
    return [
      this.categoryField(),
      this.nameField(),
      <div className="CR-two">
        <div className="Form-group">
          <label for="cr-brand">{t('composer.brand')}</label>
          <input
            id="cr-brand"
            className="FormControl"
            maxlength={100}
            value={this.brand}
            oninput={(e: InputEvent) => (this.brand = (e.target as HTMLInputElement).value)}
          />
        </div>
        {(this.category()?.itemFields() || []).map((f) => this.specField(f))}
      </div>,
      this.addingItem() ? <p className="helpText">{t('composer.first_review_help')}</p> : null,
    ];
  }

  private categoryField(): Mithril.Children {
    return (
      <div className="Form-group">
        <label for="cr-category">{t('composer.category')}</label>
        <select
          id="cr-category"
          className="FormControl"
          value={this.categoryId}
          onchange={(e: Event) => (this.categoryId = (e.target as HTMLSelectElement).value)}
        >
          {this.categories.map((c) => (
            <option value={c.id()}>{c.parentId() ? `— ${c.name()}` : c.name()}</option>
          ))}
        </select>
      </div>
    );
  }

  private nameField(): Mithril.Children {
    const max = Number(setting('name_max'));
    return (
      <div className="Form-group">
        <label for="cr-name">
          {t('composer.name')} <span className="CR-counter">{t('composer.counter', { count: this.name.length, max })}</span>
        </label>
        <input
          id="cr-name"
          className="FormControl"
          maxlength={max}
          value={this.name}
          oninput={(e: InputEvent) => this.typed((e.target as HTMLInputElement).value)}
        />
        {this.addingItem() && this.similar.length ? this.similarList() : null}
      </div>
    );
  }

  private similarList(): Mithril.Children {
    return (
      <div className="CR-similar">
        <div className="CR-similar-h">
          <i className="fas fa-circle-info" aria-hidden="true" /> {t('composer.similar')}
        </div>
        {this.similar.map((item) => (
          <a
            href={app.route('community-reviews.item', { id: item.urlId() })}
            className="CR-similar-row"
            onclick={(e: MouseEvent) => {
              e.preventDefault();
              this.hide();
              m.route.set(app.route('community-reviews.item', { id: item.urlId() }));
            }}
          >
            <span>{item.name()}</span>
            {scoreTile(item.rating(), 'CR-score--sm')}
          </a>
        ))}
      </div>
    );
  }

  private specField(f: ItemField): Mithril.Children {
    const set = (e: Event) => (this.fields[f.key] = (e.target as HTMLInputElement | HTMLSelectElement).value);
    return (
      <div className="Form-group">
        <label for={`cr-f-${f.key}`}>{f.label}</label>
        {f.type === 'choice' ? (
          <select id={`cr-f-${f.key}`} className="FormControl" value={this.fields[f.key] || ''} onchange={set}>
            <option value="">—</option>
            {f.options.map((o) => (
              <option value={o}>{o}</option>
            ))}
          </select>
        ) : (
          <input
            id={`cr-f-${f.key}`}
            className="FormControl"
            type={f.type === 'number' ? 'number' : 'text'}
            value={this.fields[f.key] || ''}
            oninput={set}
          />
        )}
      </div>
    );
  }

  /** "Already in the catalog?" while typing a new item's name (MyBB refuses duplicates on save). */
  private typed(value: string): void {
    this.name = value;
    if (!this.addingItem()) return;
    clearTimeout(this.similarTimer);
    const q = value.trim();
    if (q.length < 3) {
      this.similar = [];
      return;
    }
    this.similarTimer = window.setTimeout(() => {
      app.store
        .find<Item[]>('review-items', { filter: { q }, page: { limit: 3, total: 0 } })
        .then((items) => (this.similar = [...items]))
        .catch(() => (this.similar = []))
        .finally(() => m.redraw());
    }, 300);
  }

  private overall(): number | null {
    const criteria = this.criteria();
    let points = 0;
    let weights = 0;
    criteria.forEach((c) => {
      const s = this.scores[String(c.id())]?.score || 0;
      if (s) {
        points += s * (c.weight() || 1);
        weights += c.weight() || 1;
      }
    });
    return weights ? Math.round((points / weights) * 10) / 10 : null;
  }

  private reviewFields(): Mithril.Children {
    return [
      this.overallBox(),
      this.criteriaGroup(),
      this.input(
        'cr-title',
        [t('composer.title'), ' ', <span className="CR-muted">{t('composer.optional')}</span>],
        this.reviewTitle,
        (v) => (this.reviewTitle = v),
        150
      ),
      this.bodyField(),
      setting('ask_price_link') ? (
        <div className="CR-two">
          {this.input('cr-price', t('composer.price'), this.price, (v) => (this.price = v), 30)}
          {this.input('cr-url', t('composer.url'), this.url, (v) => (this.url = v), 255, 'url')}
        </div>
      ) : null,
      this.merchantAndOwned(),
      Number(setting('max_photos')) > 0 ? <PhotoPicker photos={this.photos} onchange={(photos: ReviewPhoto[]) => (this.photos = photos)} /> : null,
      setting('extra_pros_cons') ? (
        <div className="CR-two">
          {this.input('cr-pros', t('review.pros'), this.pros, (v) => (this.pros = v), 400, 'text', tx('composer.list_placeholder'))}
          {this.input('cr-cons', t('review.cons'), this.cons, (v) => (this.cons = v), 400, 'text', tx('composer.list_placeholder'))}
        </div>
      ) : null,
    ];
  }

  /** A labelled one-line field. */
  private input(
    id: string,
    label: Mithril.Children,
    value: string,
    set: (value: string) => void,
    max: number,
    type = 'text',
    placeholder = ''
  ): Mithril.Children {
    return (
      <div className="Form-group">
        <label for={id}>{label}</label>
        <input
          id={id}
          className="FormControl"
          type={type}
          maxlength={max}
          placeholder={placeholder}
          value={value}
          oninput={(e: InputEvent) => set((e.target as HTMLInputElement).value)}
        />
      </div>
    );
  }

  private overallBox(): Mithril.Children {
    const overall = this.overall();
    return (
      <div className="CR-overall">
        {scoreTile(overall)}
        <div>
          <div className="CR-overall-word">{overall === null ? t('composer.rate_below') : t(`words.${Math.max(1, Math.round(overall))}`)}</div>
          <div className="helpText">{t('composer.overall_help')}</div>
        </div>
      </div>
    );
  }

  private criteriaGroup(): Mithril.Children {
    const criteria = this.criteria();
    const required = !!setting('criterion_comment_required');
    const done = criteria.filter((c) => {
      const s = this.scores[String(c.id())];
      return s && s.score && (!required || s.comment.trim());
    }).length;
    return (
      <div className="Form-group">
        <span className="CR-label">
          {t(required ? 'composer.criteria_required' : 'composer.criteria')}
          <span className="CR-counter">{t('composer.done', { done, total: criteria.length })}</span>
        </span>
        {criteria.map((c) => this.criterionBox(c, required, Number(setting('comment_max'))))}
      </div>
    );
  }

  private bodyField(): Mithril.Children {
    const max = Number(setting('general_max'));
    return (
      <div className="Form-group">
        <label for="cr-body">
          {String(setting('general_label') || '') || t('review.general')}{' '}
          <span className="CR-counter">{t('composer.counter', { count: this.body.length, max })}</span>
        </label>
        <textarea
          id="cr-body"
          className="FormControl"
          rows={5}
          maxlength={max}
          value={this.body}
          oninput={(e: InputEvent) => (this.body = (e.target as HTMLTextAreaElement).value)}
        />
        <p className="helpText">{t('composer.formatting')}</p>
      </div>
    );
  }

  private merchantAndOwned(): Mithril.Children {
    if (!setting('merchant_group') && !setting('extra_verified')) return null;
    return (
      <div className="CR-two">
        {setting('merchant_group') ? (
          <div className="Form-group">
            {this.input(
              'cr-merchant',
              t('composer.merchant'),
              this.merchant,
              (v) => (this.merchant = v),
              100,
              'text',
              tx('composer.merchant_placeholder')
            )}
            <p className="helpText">{t('composer.merchant_help')}</p>
          </div>
        ) : null}
        {setting('extra_verified') ? (
          <div className="Form-group">
            <label for="cr-owned">{t('composer.owned_for')}</label>
            <select
              id="cr-owned"
              className="FormControl"
              value={this.ownedFor}
              onchange={(e: Event) => (this.ownedFor = (e.target as HTMLSelectElement).value)}
            >
              <option value="">—</option>
              {['month', 'half', 'year', 'years', 'more'].map((k) => (
                <option value={k}>{t(`owned_option.${k}`)}</option>
              ))}
            </select>
          </div>
        ) : null}
      </div>
    );
  }

  private criterionBox(c: Criterion, required: boolean, max: number): Mithril.Children {
    const key = String(c.id());
    const s = (this.scores[key] = this.scores[key] || { score: 0, comment: '' });
    return (
      <div className={classList('CR-critbox', s.score && (!required || s.comment.trim()) && 'is-done')} key={key}>
        <div className="CR-critbox-head">
          <b id={`cr-crit-${key}`}>{c.name()}</b>
          <span className="CR-starpick" role="radiogroup" aria-labelledby={`cr-crit-${key}`}>
            {[1, 2, 3, 4, 5].map((n) => (
              <button
                type="button"
                role="radio"
                aria-checked={s.score === n ? 'true' : 'false'}
                aria-label={tx('composer.stars', { count: n })}
                className={classList('CR-starpick-star', n <= s.score && 'is-on')}
                onclick={() => (s.score = n)}
              >
                ★
              </button>
            ))}
          </span>
          <span className="CR-critbox-word">{s.score ? t(`words.${s.score}`) : ''}</span>
        </div>
        <textarea
          className="FormControl CR-critbox-comment"
          rows={2}
          maxlength={max}
          aria-label={tx('composer.criterion_comment', { name: c.name() })}
          placeholder={tx(required ? 'composer.criterion_placeholder_required' : 'composer.criterion_placeholder', { name: c.name() })}
          value={s.comment}
          oninput={(e: InputEvent) => (s.comment = (e.target as HTMLTextAreaElement).value)}
        />
      </div>
    );
  }

  private reviewAttributes(): Record<string, unknown> {
    const list = (value: string) =>
      value
        .split(',')
        .map((v) => v.trim())
        .filter(Boolean);
    const attrs: Record<string, unknown> = {
      scores: this.criteria().map((c) => ({
        criterionId: Number(c.id()),
        score: this.scores[String(c.id())]?.score || 0,
        comment: this.scores[String(c.id())]?.comment || '',
      })),
      title: this.reviewTitle,
      body: this.body,
      photos: this.photos.map((p) => p.id),
    };
    if (setting('ask_price_link')) Object.assign(attrs, { price: this.price, url: this.url });
    if (setting('merchant_group')) attrs.merchantName = this.merchant.trim().replace(/^@/, '');
    if (setting('extra_verified')) attrs.ownedFor = this.ownedFor;
    if (setting('extra_pros_cons')) Object.assign(attrs, { pros: list(this.pros), cons: list(this.cons) });
    return attrs;
  }

  onsubmit(e: SubmitEvent): void {
    e.preventDefault();
    if (this.addingItem() && this.step === 1) return this.next();
    this.loading = true;

    const { item, review, editItem } = this.attrs;
    let request: Promise<unknown>;
    if (editItem && item) {
      request = item.save({ categoryId: Number(this.categoryId), name: this.name, brand: this.brand, fields: this.fields });
    } else if (!item) {
      request = app.store
        .createRecord<Item>('review-items')
        .save({ categoryId: Number(this.categoryId), name: this.name, brand: this.brand, fields: this.fields, review: this.reviewAttributes() })
        .then((created) => m.route.set(app.route('community-reviews.item', { id: (created as Item).urlId() })));
    } else if (review) {
      request = review.save(this.reviewAttributes());
    } else {
      request = app.store.createRecord<Review>('reviews').save({ ...this.reviewAttributes(), relationships: { item } } as never);
    }

    request.then(
      () => {
        this.hide();
        this.attrs.onsaved?.(item);
      },
      (error) => {
        this.loading = false;
        this.onerror(error);
        m.redraw();
      }
    );
  }
}
