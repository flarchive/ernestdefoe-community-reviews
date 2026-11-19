import app from 'flarum/admin/app';
import Component from 'flarum/common/Component';
import Button from 'flarum/common/components/Button';
import LoadingIndicator from 'flarum/common/components/LoadingIndicator';
import classList from 'flarum/common/utils/classList';
import type Mithril from 'mithril';
import type Category from '../forum/models/Category';
import CategoryEditor from './CategoryEditor';
import { post, shown, t, tx } from './util';

/** MyBB's Categories page: the list in its order (up/down), a new category, and the selected one's editor. */
export default class CategoriesTab extends Component {
  private categories: Category[] = [];
  private busy = true;
  private selected: Category | null = null;
  private draft = '';

  oninit(vnode: Mithril.Vnode) {
    super.oninit(vnode);
    this.load();
  }

  load(): void {
    this.busy = true;
    app.store
      .find<Category[]>('review-categories')
      .then((list) => {
        this.categories = [...list].sort((a, b) => a.position() - b.position());
        this.selected = (this.selected && this.categories.find((c) => c.id() === this.selected!.id())) || this.categories[0] || null;
      })
      .catch(() => app.alerts.show({ type: 'error' }, tx('load_failed')))
      .finally(() => {
        this.busy = false;
        m.redraw();
      });
  }

  view() {
    if (this.busy) return <LoadingIndicator />;
    return (
      <div className="CRAdmin-cats">
        <div className="CRAdmin-tree">
          <ul>{this.categories.map((c, i) => this.row(c, i))}</ul>
          {!this.categories.length ? <p className="helpText">{t('categories.empty')}</p> : null}
          {this.addForm()}
        </div>
        <div className="CRAdmin-detail">
          {this.selected ? (
            <CategoryEditor
              key={this.selected.id()}
              category={this.selected}
              categories={this.categories}
              onchange={() => this.load()}
              ondelete={() => ((this.selected = null), this.load())}
            />
          ) : null}
        </div>
      </div>
    );
  }

  private row(c: Category, i: number): Mithril.Children {
    return (
      <li className={classList(this.selected === c && 'is-active', c.parentId() && 'is-child')}>
        <button type="button" className="CRAdmin-treeItem" onclick={() => (this.selected = c)}>
          {c.icon() ? <i className={c.icon()!} aria-hidden="true" /> : null} {c.name()} <span className="CRAdmin-count">{c.itemCount()}</span>
        </button>
        <span className="CRAdmin-move">
          <Button
            className="Button Button--icon Button--flat"
            icon="fas fa-arrow-up"
            disabled={i === 0}
            aria-label={tx('categories.move_up')}
            onclick={() => this.move(i, -1)}
          />
          <Button
            className="Button Button--icon Button--flat"
            icon="fas fa-arrow-down"
            disabled={i === this.categories.length - 1}
            aria-label={tx('categories.move_down')}
            onclick={() => this.move(i, 1)}
          />
        </span>
      </li>
    );
  }

  private addForm(): Mithril.Children {
    return (
      <form
        className="CRAdmin-add"
        onsubmit={(e: SubmitEvent) => {
          e.preventDefault();
          this.add();
        }}
      >
        <input
          className="FormControl"
          placeholder={tx('categories.new_placeholder')}
          aria-label={tx('categories.new_placeholder')}
          value={this.draft}
          oninput={(e: InputEvent) => (this.draft = (e.target as HTMLInputElement).value)}
        />
        <Button type="submit" className="Button" icon="fas fa-plus" disabled={!this.draft.trim()}>
          {t('categories.add')}
        </Button>
      </form>
    );
  }

  private move(index: number, step: number): void {
    const list = [...this.categories];
    const [moved] = list.splice(index, 1);
    list.splice(index + step, 0, moved);
    this.categories = list;
    post('/review-categories/order', { order: list.map((c) => Number(c.id())) })
      .then(() => this.load())
      .catch(() => this.load());
  }

  private add(): void {
    const name = this.draft.trim();
    if (!name) return;
    app.store
      .createRecord<Category>('review-categories')
      .save({ name })
      .then((c) => {
        this.draft = '';
        this.selected = c as Category;
        this.load();
      })
      .catch(shown);
  }
}
