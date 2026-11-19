import app from 'flarum/admin/app';
import Component from 'flarum/common/Component';
import Button from 'flarum/common/components/Button';
import Switch from 'flarum/common/components/Switch';
import saveSettings from 'flarum/admin/utils/saveSettings';
import type Mithril from 'mithril';
import { EXT, shown, t, tx } from './util';

/** A setting field: its key, type, and for selects the options (string keys, so JS keeps their order). */
interface Field {
  key: string;
  type: 'number' | 'text' | 'bool' | 'select';
  options?: string[];
  min?: number;
}

const num = (key: string, min = 1): Field => ({ key, type: 'number', min });
const bool = (key: string): Field => ({ key, type: 'bool' });
const text = (key: string): Field => ({ key, type: 'text' });
const select = (key: string, options: string[] = []): Field => ({ key, type: 'select', options });

/** MyBB's settings, grouped, then our extras. Every one has a control here (mybb-parity.md). */
const GROUPS: { id: string; fields: Field[] }[] = [
  {
    id: 'lists',
    fields: [
      select('item_order', ['name', 'rating', 'reviews', 'views', 'date']),
      select('item_order_dir', ['asc', 'desc']),
      select('display_order', ['desc', 'asc']),
      num('items_per_page'),
      num('reviews_per_page'),
      num('comments_per_page'),
      num('recent_limit'),
      num('widget_limit'),
      bool('index_widget'),
      num('rank_min_reviews'),
    ],
  },
  { id: 'items', fields: [num('name_max'), num('card_name_max'), num('flood_seconds', 0), bool('item_approval'), bool('one_per_member')] },
  {
    id: 'writing',
    fields: [
      text('general_label'),
      num('general_max'),
      num('comment_max'),
      bool('criterion_comment_required'),
      bool('ask_price_link'),
      bool('review_approval'),
    ],
  },
  { id: 'photos', fields: [num('max_photos', 0), bool('require_photo'), select('photo_host', ['local', 'imgur']), text('imgur_client_id')] },
  { id: 'merchants', fields: [select('merchant_group')] },
  { id: 'extras', fields: [bool('extra_helpful'), bool('extra_verified'), bool('extra_pros_cons'), bool('extra_rich_results')] },
];

/** Every MyBB setting, grouped, saved together. */
export default class SettingsTab extends Component {
  private values: Record<string, string> = {};
  private saving = false;

  oninit(vnode: Mithril.Vnode) {
    super.oninit(vnode);
    GROUPS.forEach((g) => g.fields.forEach((f) => (this.values[f.key] = String(app.data.settings[`${EXT}.${f.key}`] ?? ''))));
  }

  view() {
    return (
      <form
        className="CRAdmin-settings"
        onsubmit={(e: SubmitEvent) => {
          e.preventDefault();
          this.save();
        }}
      >
        {GROUPS.map((group) => (
          <section className="CRAdmin-section">
            <h3>{t(`settings.${group.id}`)}</h3>
            {group.id === 'extras' ? <p className="helpText">{t('settings.extras_help')}</p> : null}
            <div className="CRAdmin-grid">{group.fields.map((f) => this.field(f))}</div>
          </section>
        ))}
        <Button type="submit" className="Button Button--primary" loading={this.saving}>
          {t('settings.save')}
        </Button>
        <p className="helpText">{t('settings.permissions_note')}</p>
      </form>
    );
  }

  private help(key: string): Mithril.Children {
    const id = `${EXT}.admin.settings.${key}_help`;
    const help = app.translator.trans(id, {}, true) as unknown as string;
    return help && help !== id ? <div className="helpText">{help}</div> : null;
  }

  private field(f: Field): Mithril.Children {
    if (f.type === 'bool') {
      return (
        <div className="Form-group CRAdmin-bool">
          <Switch
            state={this.values[f.key] === '1' || this.values[f.key] === 'true'}
            onchange={(on: boolean) => (this.values[f.key] = on ? '1' : '0')}
          >
            {t(`settings.${f.key}`)}
          </Switch>
          {this.help(f.key)}
        </div>
      );
    }
    return (
      <div className="Form-group">
        <label>{t(`settings.${f.key}`)}</label>
        {f.type === 'select' ? this.select(f) : this.input(f)}
        {this.help(f.key)}
      </div>
    );
  }

  private input(f: Field): Mithril.Children {
    return (
      <input
        className="FormControl"
        type={f.type === 'number' ? 'number' : 'text'}
        min={f.min}
        value={this.values[f.key]}
        oninput={(e: InputEvent) => (this.values[f.key] = (e.target as HTMLInputElement).value)}
      />
    );
  }

  /** A select; the Merchants group lists the forum's own groups (not Admin, Guest, Member, Mod). */
  private select(f: Field): Mithril.Children {
    const options =
      f.key === 'merchant_group'
        ? [
            { value: '0', label: tx('settings.no_group') },
            ...app.store
              .all('groups')
              .filter((g) => Number(g.id()) > 4)
              .map((g) => ({ value: String(g.id()), label: String(g.attribute('namePlural')) })),
          ]
        : (f.options || []).map((o) => ({ value: o, label: tx(`settings.${f.key}_${o}`) }));
    return (
      <select
        className="FormControl"
        value={this.values[f.key] || options[0]?.value}
        onchange={(e: Event) => (this.values[f.key] = (e.target as HTMLSelectElement).value)}
      >
        {options.map((o) => (
          <option value={o.value}>{o.label}</option>
        ))}
      </select>
    );
  }

  private save(): void {
    this.saving = true;
    const payload: Record<string, string> = {};
    Object.entries(this.values).forEach(([key, value]) => (payload[`${EXT}.${key}`] = value));
    saveSettings(payload)
      .then(() => app.alerts.show({ type: 'success' }, tx('settings.saved')))
      .catch(shown)
      .finally(() => {
        this.saving = false;
        m.redraw();
      });
  }
}
