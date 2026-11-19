import app from 'flarum/admin/app';
import Component from 'flarum/common/Component';
import type { ComponentAttrs } from 'flarum/common/Component';
import Button from 'flarum/common/components/Button';
import type Mithril from 'mithril';
import type Category from '../forum/models/Category';
import type Criterion from '../forum/models/Criterion';
import CriterionRow from './CriterionRow';
import FieldsEditor from './FieldsEditor';
import { shown, t, tx } from './util';

interface Attrs extends ComponentAttrs {
  category: Category;
  categories: Category[];
  onchange: () => void;
  ondelete: () => void;
}

/** One category: its details, its fields to rate (MyBB "fields") and its product details. */
export default class CategoryEditor extends Component<Attrs> {
  private draft = '';

  view() {
    const { category } = this.attrs;
    return (
      <div>
        {this.details(category)}
        {this.criteria(category)}
        <FieldsEditor category={category} save={(attrs: Record<string, unknown>) => this.save(attrs)} />
        <section className="CRAdmin-section CRAdmin-danger">
          <Button className="Button Button--danger" icon="far fa-trash-can" onclick={() => this.remove()}>
            {t('categories.delete')}
          </Button>
        </section>
      </div>
    );
  }

  private save(attrs: Record<string, unknown>): Promise<void> {
    return this.attrs.category
      .save(attrs)
      .then(() => m.redraw())
      .catch(shown);
  }

  private details(category: Category): Mithril.Children {
    const parents = this.attrs.categories.filter((c) => !c.parentId() && c !== category);
    return (
      <section className="CRAdmin-section">
        <h3>{t('categories.details')}</h3>
        <div className="CRAdmin-grid">
          {this.text(tx('categories.name'), category.name(), (v) => this.save({ name: v }))}
          {this.text(tx('categories.icon'), category.icon() || '', (v) => this.save({ icon: v || null }), 'fas fa-cut')}
          {this.text(tx('categories.color'), category.color() || '', (v) => this.save({ color: v || null }), '#4d698e')}
          <div className="Form-group">
            <label>{t('categories.parent')}</label>
            <select
              className="FormControl"
              value={category.parentId() ? String(category.parentId()) : ''}
              onchange={(e: Event) => this.save({ parentId: Number((e.target as HTMLSelectElement).value) || null })}
            >
              <option value="">{t('categories.top_level')}</option>
              {parents.map((p) => (
                <option value={p.id()}>{p.name()}</option>
              ))}
            </select>
          </div>
        </div>
        {this.text(tx('categories.description'), category.description() || '', (v) => this.save({ description: v || null }))}
      </section>
    );
  }

  private text(label: string, value: string, onsave: (value: string) => unknown, placeholder = ''): Mithril.Children {
    return (
      <div className="Form-group">
        <label>{label}</label>
        <input
          className="FormControl"
          value={value}
          placeholder={placeholder}
          onchange={(e: Event) => {
            const next = (e.target as HTMLInputElement).value.trim();
            if (next !== value) onsave(next);
          }}
        />
      </div>
    );
  }

  private criteria(category: Category): Mithril.Children {
    const list = ((category.criteria() || []).filter(Boolean) as Criterion[]).sort((a, b) => a.position() - b.position());
    const others = this.attrs.categories.filter((c) => c !== category);
    return (
      <section className="CRAdmin-section">
        <h3>{t('criteria.title')}</h3>
        <p className="helpText">{t('criteria.help')}</p>
        <ul className="CRAdmin-criteria">
          {list.map((c, i) => (
            <CriterionRow key={c.id()} criterion={c} index={i} all={list} others={others} onchange={this.attrs.onchange} />
          ))}
        </ul>
        <form
          className="CRAdmin-add"
          onsubmit={(e: SubmitEvent) => {
            e.preventDefault();
            this.addCriterion(category);
          }}
        >
          <input
            className="FormControl"
            placeholder={tx('criteria.new_placeholder')}
            aria-label={tx('criteria.new_placeholder')}
            value={this.draft}
            oninput={(e: InputEvent) => (this.draft = (e.target as HTMLInputElement).value)}
          />
          <Button type="submit" className="Button" icon="fas fa-plus" disabled={!this.draft.trim()}>
            {t('criteria.add')}
          </Button>
        </form>
      </section>
    );
  }

  private addCriterion(category: Category): void {
    const name = this.draft.trim();
    if (!name) return;
    app.store
      .createRecord<Criterion>('review-criteria')
      .save({ name, weight: 1, relationships: { category } } as never)
      .then(() => {
        this.draft = '';
        this.attrs.onchange();
      })
      .catch(shown);
  }

  private remove(): void {
    const { category } = this.attrs;
    if (!confirm(tx('categories.delete_confirm', { name: category.name() }))) return;
    category
      .delete()
      .then(() => this.attrs.ondelete())
      .catch(shown);
  }
}
