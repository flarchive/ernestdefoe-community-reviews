import Component from 'flarum/common/Component';
import type { ComponentAttrs } from 'flarum/common/Component';
import Button from 'flarum/common/components/Button';
import type Mithril from 'mithril';
import type Category from '../forum/models/Category';
import type { ItemField } from '../forum/models/Category';
import { t, tx } from './util';

interface Attrs extends ComponentAttrs {
  category: Category;
  save: (attrs: Record<string, unknown>) => unknown;
}

/** Product details (optional spec fields): asked for when a product is added, shown on its page. */
export default class FieldsEditor extends Component<Attrs> {
  private fields(): ItemField[] {
    return [...(this.attrs.category.itemFields() || [])];
  }

  private commit(next: ItemField[]): void {
    this.attrs.save({ itemFields: next });
  }

  private change(index: number, patch: Partial<ItemField>): void {
    this.commit(this.fields().map((f, i) => (i === index ? { ...f, ...patch } : f)));
  }

  view() {
    return (
      <section className="CRAdmin-section">
        <h3>{t('fields.title')}</h3>
        <p className="helpText">{t('fields.help')}</p>
        {this.fields().map((f, i) => this.row(f, i))}
        <Button
          className="Button"
          icon="fas fa-plus"
          onclick={() => this.commit([...this.fields(), { key: '', label: tx('fields.new_label'), type: 'text', options: [], filter: false }])}
        >
          {t('fields.add')}
        </Button>
      </section>
    );
  }

  private row(f: ItemField, i: number): Mithril.Children {
    return (
      <div className="CRAdmin-field">
        <input
          className="FormControl"
          value={f.label}
          aria-label={tx('fields.label')}
          onchange={(e: Event) => this.change(i, { label: (e.target as HTMLInputElement).value })}
        />
        <select
          className="FormControl"
          value={f.type}
          aria-label={tx('fields.type')}
          onchange={(e: Event) => this.change(i, { type: (e.target as HTMLSelectElement).value as ItemField['type'] })}
        >
          {['text', 'number', 'choice'].map((type) => (
            <option value={type}>{t(`fields.type_${type}`)}</option>
          ))}
        </select>
        {f.type === 'choice' ? (
          <input
            className="FormControl"
            value={f.options.join(', ')}
            aria-label={tx('fields.options')}
            placeholder={tx('fields.options')}
            onchange={(e: Event) => this.change(i, { options: this.options((e.target as HTMLInputElement).value) })}
          />
        ) : (
          <span />
        )}
        <Button
          className="Button Button--icon Button--flat"
          icon="far fa-trash-can"
          aria-label={tx('fields.delete')}
          onclick={() => this.commit(this.fields().filter((_, j) => j !== i))}
        />
      </div>
    );
  }

  private options(value: string): string[] {
    return value
      .split(',')
      .map((o) => o.trim())
      .filter(Boolean);
  }
}
