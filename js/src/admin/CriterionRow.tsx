import app from 'flarum/admin/app';
import Component from 'flarum/common/Component';
import type { ComponentAttrs } from 'flarum/common/Component';
import Button from 'flarum/common/components/Button';
import type Mithril from 'mithril';
import type Category from '../forum/models/Category';
import type Criterion from '../forum/models/Criterion';
import { post, shown, t, tx } from './util';

interface Attrs extends ComponentAttrs {
  criterion: Criterion;
  index: number;
  all: Criterion[];
  others: Category[];
  onchange: () => void;
}

/** One field to rate: its name, weight, order, MyBB's "Add to category…" copy, and delete. */
export default class CriterionRow extends Component<Attrs> {
  view() {
    const { criterion: c, index, all } = this.attrs;
    return (
      <li className="CRAdmin-criterion">
        <input
          className="FormControl"
          value={c.name()}
          aria-label={tx('criteria.name')}
          onchange={(e: Event) => c.save({ name: (e.target as HTMLInputElement).value.trim() }).catch(shown)}
        />
        {this.weight(c)}
        <Button
          className="Button Button--icon Button--flat"
          icon="fas fa-arrow-up"
          disabled={index === 0}
          aria-label={tx('criteria.move_up')}
          onclick={() => this.move(-1)}
        />
        <Button
          className="Button Button--icon Button--flat"
          icon="fas fa-arrow-down"
          disabled={index === all.length - 1}
          aria-label={tx('criteria.move_down')}
          onclick={() => this.move(1)}
        />
        {this.copy(c)}
        <Button
          className="Button Button--icon Button--flat"
          icon="far fa-trash-can"
          aria-label={tx('criteria.delete')}
          onclick={() => this.remove()}
        />
      </li>
    );
  }

  private weight(c: Criterion): Mithril.Children {
    return (
      <label className="CRAdmin-weight">
        {t('criteria.weight')}
        <select
          className="FormControl"
          value={String(c.weight())}
          onchange={(e: Event) => c.save({ weight: Number((e.target as HTMLSelectElement).value) }).catch(shown)}
        >
          {['1', '2', '3', '4', '5'].map((w) => (
            <option value={w}>×{w}</option>
          ))}
        </select>
      </label>
    );
  }

  private copy(c: Criterion): Mithril.Children {
    if (!this.attrs.others.length) return null;
    return (
      <select
        className="FormControl CRAdmin-copy"
        aria-label={tx('criteria.copy')}
        value=""
        onchange={(e: Event) => {
          const target = (e.target as HTMLSelectElement).value;
          if (!target) return;
          post(`/review-criteria/${c.id()}/copy`, { categoryId: Number(target) })
            .then(() => {
              app.alerts.show({ type: 'success' }, tx('criteria.copied'));
              this.attrs.onchange();
            })
            .catch(shown);
        }}
      >
        <option value="">{t('criteria.copy')}</option>
        {this.attrs.others.map((o) => (
          <option value={o.id()}>{o.name()}</option>
        ))}
      </select>
    );
  }

  private move(step: number): void {
    const list = [...this.attrs.all];
    const [moved] = list.splice(this.attrs.index, 1);
    list.splice(this.attrs.index + step, 0, moved);
    post('/review-criteria/order', { order: list.map((x) => Number(x.id())) })
      .then(() => this.attrs.onchange())
      .catch(() => this.attrs.onchange());
  }

  private remove(): void {
    const c = this.attrs.criterion;
    if (!confirm(tx('criteria.delete_confirm', { name: c.name() }))) return;
    c.delete()
      .then(() => this.attrs.onchange())
      .catch(shown);
  }
}
