import app from 'flarum/admin/app';
import Component from 'flarum/common/Component';
import Button from 'flarum/common/components/Button';
import { post, shown, t, tx } from './util';

/** MyBB's ACP "Merge Products" by ID (the forum's item page also merges, by name). */
export default class MergeTab extends Component {
  private source = '';
  private target = '';
  private merging = false;

  view() {
    return (
      <form
        className="CRAdmin-section"
        onsubmit={(e: SubmitEvent) => {
          e.preventDefault();
          this.merge();
        }}
      >
        <h3>{t('merge.title')}</h3>
        <p className="helpText">{t('merge.help')}</p>
        <div className="CRAdmin-grid">
          <div className="Form-group">
            <label>{t('merge.source')}</label>
            <input
              className="FormControl"
              type="number"
              min="1"
              value={this.source}
              oninput={(e: InputEvent) => (this.source = (e.target as HTMLInputElement).value)}
            />
          </div>
          <div className="Form-group">
            <label>{t('merge.target')}</label>
            <input
              className="FormControl"
              type="number"
              min="1"
              value={this.target}
              oninput={(e: InputEvent) => (this.target = (e.target as HTMLInputElement).value)}
            />
          </div>
        </div>
        <Button type="submit" className="Button Button--primary" loading={this.merging} disabled={!this.source || !this.target}>
          {t('merge.submit')}
        </Button>
      </form>
    );
  }

  private merge(): void {
    if (!confirm(tx('merge.confirm', { source: this.source, target: this.target }))) return;
    this.merging = true;
    post(`/review-items/${Number(this.source)}/merge`, { targetId: Number(this.target) })
      .then(() => {
        app.alerts.show({ type: 'success' }, tx('merge.done'));
        this.source = '';
        this.target = '';
      })
      .catch(shown)
      .finally(() => {
        this.merging = false;
        m.redraw();
      });
  }
}
